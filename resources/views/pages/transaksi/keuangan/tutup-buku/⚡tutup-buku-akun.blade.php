<?php

/**
 * Tutup Buku — Saldo Akun (Kas & Neraca) → TKTXN_SALDOAWALAKUNS tahun T+1.
 * Rumus = tutup buku Forms (App\Support\Keuangan\TutupBukuAkun): akun neraca saldo awal + arus jurnal
 * setahun, L01 LABA (RUGI) sebagai penyeimbang. Hitungan per cabang jurnal (±5 dtk) — dijalankan lewat
 * tombol Hitung, hasilnya disimpan sebagai properti (≤ 50 baris), bukan dihitung tiap render.
 * Default TIDAK menimpa baris tahun depan yang sudah ada (bisa berisi koreksi manual).
 */

use Livewire\Component;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Support\Keuangan\TutupBukuAkun;

new class extends Component {
    private const ROLE_TUTUP_BUKU = ['Admin'];

    public int $tahun = 0;
    /** Hasil Hitung — kosong = belum dihitung untuk tahun ini. */
    public array $baris = [];
    public ?int $tahunDihitung = null;
    public bool $timpa = false;
    public bool $hanyaBerubah = false;
    public bool $konfirmasi = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(self::ROLE_TUTUP_BUKU), 403, 'Tutup buku khusus Admin.');
        $this->tahun = (int) Carbon::now()->year - 1;
    }

    public function updatedTahun(): void
    {
        $this->tahun = max(2020, min((int) $this->tahun, (int) Carbon::now()->year));
        $this->reset(['baris', 'tahunDihitung', 'konfirmasi']);
    }

    public function hitung(): void
    {
        $this->baris = collect(TutupBukuAkun::pratinjau($this->tahun))->map(function ($row) {
            $row['status'] = !$row['ada'] ? 'baru' : (TutupBukuAkun::sama($row['lama'], $row['baru']) ? 'sama' : 'beda');

            return $row;
        })->all();
        $this->tahunDihitung = $this->tahun;
    }

    public function ringkasan(): array
    {
        $b = collect($this->baris);
        $d = $b->where('dk', 'D')->sum('baru');
        $k = $b->where('dk', 'K')->sum('baru');

        return [
            'akun' => $b->count(),
            'sama' => $b->where('status', 'sama')->count(),
            'beda' => $b->where('status', 'beda')->count(),
            'baru' => $b->where('status', 'baru')->count(),
            'kas' => $b->filter(fn ($r) => str_starts_with($r['accId'], '1.1A01'))->sum('baru'),
            'selisihDK' => $d - $k,
        ];
    }

    public function siapkanProses(): void
    {
        if (!auth()->user()?->hasAnyRole(self::ROLE_TUTUP_BUKU)) {
            $this->dispatch('toast', type: 'error', message: 'Tutup buku khusus Admin.');
            return;
        }
        if ($this->tahunDihitung !== $this->tahun) {
            $this->dispatch('toast', type: 'warning', message: 'Klik Hitung dulu untuk tahun ' . $this->tahun . '.');
            return;
        }
        $rk = $this->ringkasan();
        if ($rk['baru'] === 0 && ($rk['beda'] === 0 || !$this->timpa)) {
            $this->dispatch('toast', type: 'info', message: 'Tidak ada yang perlu ditulis' . ($rk['beda'] > 0 ? ' (akun beda dilewati — nyalakan "Timpa" bila memang ingin menghitung ulang).' : '.'));
            return;
        }
        $this->konfirmasi = true;
    }

    public function batalProses(): void
    {
        $this->konfirmasi = false;
    }

    public function proses(): void
    {
        $this->konfirmasi = false;
        if (!auth()->user()?->hasAnyRole(self::ROLE_TUTUP_BUKU) || $this->tahunDihitung !== $this->tahun) {
            $this->dispatch('toast', type: 'error', message: 'Tutup buku khusus Admin / hitung ulang dulu.');
            return;
        }

        try {
            // Hitung ulang di server — properti publik tidak dipakai menulis.
            $hasil = TutupBukuAkun::proses($this->tahun, $this->timpa);
        } catch (QueryException $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal tutup buku akun: ' . $e->getMessage());
            return;
        }

        Log::info('Tutup buku saldo akun', ['tahun_ditutup' => $this->tahun, 'timpa' => $this->timpa, 'user' => auth()->user()?->myuser_code] + $hasil);
        $this->hitung();
        $this->dispatch('toast', type: 'success', message: "Saldo awal akun {$hasil['tahunBaru']}: {$hasil['dibuat']} dibuat, {$hasil['diubah']} diperbarui, {$hasil['sama']} sesuai, {$hasil['dilewati']} dilewati.");
    }
};
?>

<div class="space-y-4">
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-end gap-4 p-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:bg-gray-900 dark:border-gray-700">
        <div>
            <x-input-label value="Tahun yang ditutup" />
            <x-select-input wire:model.live="tahun" class="mt-1 w-36">
                @for ($th = (int) now()->year; $th >= 2020; $th--)
                    <option value="{{ $th }}">{{ $th }}</option>
                @endfor
            </x-select-input>
        </div>
        <div class="pb-2 text-sm text-muted dark:text-gray-400">→ saldo awal akun <strong class="text-ink dark:text-gray-100">{{ $tahun + 1 }}</strong></div>
        <x-secondary-button type="button" wire:click="hitung" class="h-11" wire:loading.attr="disabled" wire:target="hitung">
            <span wire:loading.remove wire:target="hitung" class="inline-flex items-center gap-2"><x-ikon nama="cari" /> Hitung</span>
            <span wire:loading wire:target="hitung"><x-loading /> Menghitung jurnal setahun...</span>
        </x-secondary-button>
        <div class="pb-1.5">
            <x-toggle wire:model.live="hanyaBerubah" :trueValue="true" :falseValue="false" label="Hanya yang berubah / baru" />
        </div>
        <div class="pb-1.5">
            <x-toggle wire:model.live="timpa" :trueValue="true" :falseValue="false" label="Timpa akun yang beda"
                title="Mati (default) = baris tahun depan yang sudah ada tidak diubah (bisa berisi koreksi manual). Nyala = hitung ulang & timpa." />
        </div>
        <div class="ml-auto">
            <x-primary-button type="button" wire:click="siapkanProses" class="h-11" :disabled="$tahunDihitung !== $tahun">
                <x-ikon nama="proses" /> Proses Tutup Buku {{ $tahun }}
            </x-primary-button>
        </div>
    </div>

    @if ($tahun >= (int) now()->year)
        <div class="px-4 py-2 text-sm border rounded-xl text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
            Tahun {{ $tahun }} belum berakhir — angka masih akan berubah. Boleh diproses, tetapi jalankan ulang (dengan "Timpa") setelah 31 Desember.
        </div>
    @endif

    @if ($tahunDihitung !== $tahun)
        <div class="px-6 py-12 text-sm text-center border border-dashed rounded-2xl text-muted border-hairline bg-canvas dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400">
            Klik <strong>Hitung</strong> untuk menghitung saldo awal akun {{ $tahun + 1 }} dari jurnal tahun {{ $tahun }} (± 5 detik).
        </div>
    @else
        @php $rk = $this->ringkasan(); @endphp
        <div class="flex flex-wrap items-baseline px-5 py-2.5 text-sm border gap-x-6 gap-y-1 border-hairline rounded-xl bg-canvas dark:bg-gray-900 dark:border-gray-700">
            <span class="text-muted dark:text-gray-400">Akun <span class="ml-1 font-medium text-ink dark:text-gray-100">{{ $rk['akun'] }}</span></span>
            <span class="text-muted dark:text-gray-400">Σ Kas &amp; Bank awal {{ $tahun + 1 }} <span class="ml-1 font-mono font-medium text-ink dark:text-gray-100">Rp {{ number_format($rk['kas'], 0, ',', '.') }}</span></span>
            <span class="text-muted dark:text-gray-400" title="Σ akun D − Σ akun K. L01 dibuat sebagai penyeimbang sehingga angka ini sama dengan tahun sebelumnya.">Σ D − Σ K <span class="ml-1 font-mono font-medium text-ink dark:text-gray-100">{{ number_format($rk['selisihDK'], 0, ',', '.') }}</span></span>
            <span class="text-muted dark:text-gray-400">Sama <span class="ml-1 font-medium text-emerald-700 dark:text-emerald-400">{{ $rk['sama'] }}</span></span>
            <span class="text-muted dark:text-gray-400">Beda <span class="ml-1 font-medium text-rose-700 dark:text-rose-300">{{ $rk['beda'] }}</span></span>
            <span class="text-muted dark:text-gray-400">Baru <span class="ml-1 font-medium text-blue-700 dark:text-blue-300">{{ $rk['baru'] }}</span></span>
        </div>

        <div class="overflow-auto border shadow-sm max-h-[60vh] bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full min-w-full text-sm">
                <thead class="sticky top-0 z-10 text-left text-muted bg-surface-soft dark:bg-gray-800 dark:text-gray-300">
                    <tr class="text-xs font-semibold tracking-wide uppercase">
                        <th class="px-4 py-2">Akun</th>
                        <th class="px-3 py-2 text-center">D/K</th>
                        <th class="px-3 py-2 text-right">Awal {{ $tahun }}</th>
                        <th class="px-3 py-2 text-right">Debit {{ $tahun }}</th>
                        <th class="px-3 py-2 text-right">Kredit {{ $tahun }}</th>
                        <th class="px-3 py-2 text-right">Awal {{ $tahun + 1 }}</th>
                        <th class="px-3 py-2 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-body divide-hairline dark:divide-gray-700 dark:text-gray-200">
                    @foreach ($baris as $row)
                        @continue($hanyaBerubah && $row['status'] === 'sama')
                        <tr wire:key="tb-akun-{{ $row['accId'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60 {{ $row['laba'] ? 'bg-violet-50/60 dark:bg-violet-900/10' : '' }}">
                            <td class="px-4 py-2">
                                <span class="font-mono text-xs text-muted dark:text-gray-400">{{ $row['accId'] }}</span>
                                <span class="ml-1 text-ink dark:text-gray-100">{{ $row['nama'] }}</span>
                                @if ($row['laba'])
                                    <div class="text-xs text-violet-700 dark:text-violet-300">Penyeimbang — menyerap laba/rugi tahun {{ $tahun }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">{{ $row['dk'] }}</td>
                            <td class="px-3 py-2 font-mono text-right whitespace-nowrap">{{ number_format($row['awal'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 font-mono text-right whitespace-nowrap">{{ number_format($row['debit'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 font-mono text-right whitespace-nowrap">{{ number_format($row['kredit'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 font-mono font-semibold text-right whitespace-nowrap">
                                {{ number_format($row['baru'], 0, ',', '.') }}
                                @if ($row['status'] === 'beda')
                                    <div class="text-xs font-normal text-rose-700 dark:text-rose-300">tersimpan {{ number_format($row['lama'], 0, ',', '.') }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if ($row['status'] === 'sama')
                                    <x-badge variant="success">Sama</x-badge>
                                @elseif ($row['status'] === 'beda')
                                    <x-badge variant="danger">Beda</x-badge>
                                @else
                                    <x-badge variant="info">Baru</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($konfirmasi)
        @php $rk = $this->ringkasan(); @endphp
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-gray-900/50" x-data x-init="$nextTick(() => $refs.btnYaAkun?.focus())">
            <div class="w-full max-w-md overflow-hidden border shadow-xl bg-canvas rounded-2xl border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="px-5 py-4 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-base font-semibold text-ink dark:text-gray-100">Tutup Buku Saldo Akun {{ $tahun }}</h3>
                    <p class="mt-0.5 text-sm text-muted dark:text-gray-400">Saldo awal akun {{ $tahun + 1 }} akan ditulis (dihitung ulang di server).</p>
                </div>
                <dl class="px-5 py-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Baris baru dibuat</dt><dd class="font-medium text-ink dark:text-gray-100">{{ $rk['baru'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted dark:text-gray-400">Akun beda</dt><dd class="font-medium text-rose-700 dark:text-rose-300">{{ $rk['beda'] }} — {{ $timpa ? 'DITIMPA' : 'dilewati' }}</dd></div>
                    @if ($timpa && $rk['beda'] > 0)
                        <p class="px-3 py-2 text-xs border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:text-amber-200 dark:bg-amber-900/20 dark:border-amber-700">
                            Koreksi manual di saldo awal {{ $tahun + 1 }} (mis. KAS yang diisi tangan) akan hilang. Neraca & Saldo Kas tahun {{ $tahun + 1 }} ikut berubah. Angka lama dicatat di log.
                        </p>
                    @endif
                </dl>
                <div class="flex justify-end gap-2 px-5 py-4 border-t border-hairline bg-surface-soft dark:bg-gray-800/50 dark:border-gray-700">
                    <x-secondary-button type="button" wire:click="batalProses"><x-ikon nama="batal" /> Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="proses" x-ref="btnYaAkun" wire:loading.attr="disabled" wire:target="proses">
                        <span wire:loading.remove wire:target="proses" class="inline-flex items-center gap-2"><x-ikon nama="proses" /> Ya, proses</span>
                        <span wire:loading wire:target="proses"><x-loading /> Memproses...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    @endif
</div>
