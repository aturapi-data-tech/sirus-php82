<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/kamar-operasi/rm-daftar-kamar-operasi-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Support\KamarOperasiTarif;

/**
 * Daftar operasi yang sudah dikirim untuk SATU kunjungan rawat inap — baca saja.
 *
 * Sejajar `rm-daftar-laborat-ri`. Total yang ditampilkan adalah 11 pos tagihan
 * pasien; jasa on call sengaja tidak ikut karena tidak ditagihkan.
 */
new class extends Component {
    public ?string $riHdrNo = null;
    public array $rows = [];

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
        $this->findData();
    }

    #[On('kamar-operasi-ri.updated')]
    #[On('refresh-after-kamar-operasi.saved')]
    public function findData(): void
    {
        if (empty($this->riHdrNo)) {
            $this->rows = [];
            return;
        }

        $totalPos = collect(array_keys(KamarOperasiTarif::POS))
            ->map(fn($kolom) => "NVL(o.{$kolom},0)")
            ->implode(' + ');

        $this->rows = DB::table('rstxn_oks as o')
            ->leftJoin('rsmst_doctors as dopr', 'dopr.dr_id', '=', 'o.dr_id')
            ->leftJoin('rsmst_doctors as danes', 'danes.dr_id', '=', 'o.dr_id_ok')
            ->leftJoin('rsmst_mstdiags as dg', 'dg.diag_id', '=', 'o.diag_id')
            ->select(
                'o.ok_reg',
                'o.ok_status',
                DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi') as ok_date"),
                'dopr.dr_name as operator_name',
                'danes.dr_name as anestesi_name',
                'dg.diag_desc',
                DB::raw("({$totalPos}) as total_fee"),
                DB::raw("(SELECT string_agg(a.accdoc_desc) FROM rstxn_okacts t JOIN rsmst_accdocs a ON a.accdoc_id = t.accdoc_id WHERE t.ok_reg = o.ok_reg) AS tindakan_desc"),
            )
            ->where('o.rihdr_no', $this->riHdrNo)
            ->orderByDesc('o.ok_reg')
            ->get()
            ->map(fn($transaksiOk) => (array) $transaksiOk)
            ->toArray();
    }
};
?>

<div>
    <div class="overflow-hidden border border-hairline rounded-xl dark:border-gray-700">
        <div class="flex items-center justify-between px-4 py-2 border-b border-hairline bg-surface-soft dark:border-gray-700 dark:bg-gray-800/50">
            <h4 class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">Operasi yang sudah dikirim</h4>
            <x-badge variant="gray">{{ count($rows) }}</x-badge>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold tracking-wide uppercase text-muted bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-2">No Txn</th>
                        <th class="px-4 py-2">Tanggal</th>
                        <th class="px-4 py-2">Tindakan / Dokter</th>
                        <th class="px-4 py-2 text-right">Tagihan Pasien</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($rows as $row)
                        @php
                            $statusOk = strtoupper($row['ok_status'] ?? 'A');
                            $statusOk = $statusOk !== '' ? $statusOk : 'A';
                            [$statusText, $statusVariant] = match ($statusOk) {
                                'A' => ['Proses Transaksi', 'warning'],
                                'L' => ['Transaksi Selesai', 'success'],
                                'F' => ['Dibatalkan', 'error'],
                                default => [$statusOk, 'gray'],
                            };
                        @endphp
                        <tr wire:key="daftar-ok-ri-{{ $row['ok_reg'] }}">
                            <td class="px-4 py-1.5 font-mono text-muted">{{ $row['ok_reg'] }}</td>
                            <td class="px-4 py-1.5 font-mono text-sm text-body dark:text-gray-300 whitespace-nowrap">{{ $row['ok_date'] ?? '-' }}</td>
                            <td class="px-4 py-1.5 space-y-0.5">
                                <div class="text-ink dark:text-gray-200">{{ $row['tindakan_desc'] ?: 'Belum ada tindakan' }}</div>
                                <div class="text-xs text-muted">
                                    Operator: {{ $row['operator_name'] ?? '-' }} &middot; Anestesi: {{ $row['anestesi_name'] ?? '-' }}
                                </div>
                                @if (!empty($row['diag_desc']))
                                    <div class="text-xs">
                                        <span class="text-muted">Diagnosa pra-op:</span>
                                        <span class="ml-1 font-medium text-amber-700 dark:text-amber-400">{{ $row['diag_desc'] }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-1.5 font-semibold text-right text-ink dark:text-gray-200 tabular-nums whitespace-nowrap">
                                Rp {{ number_format($row['total_fee'] ?? 0) }}
                                @if ($statusOk === 'A')
                                    <div class="text-xs font-normal italic text-muted">belum masuk tagihan</div>
                                @endif
                            </td>
                            <td class="px-4 py-1.5">
                                <x-badge :variant="$statusVariant">{{ $statusText }}</x-badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-sm text-center text-muted-soft">
                                Belum ada pasien dikirim ke kamar operasi
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
