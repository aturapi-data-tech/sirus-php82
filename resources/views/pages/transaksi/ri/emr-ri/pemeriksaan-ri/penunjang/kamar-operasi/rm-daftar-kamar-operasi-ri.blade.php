<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/kamar-operasi/rm-daftar-kamar-operasi-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

/**
 * Daftar operasi yang sudah dikirim untuk SATU kunjungan rawat inap — baca saja.
 *
 * Model tabelnya disamakan dengan `rm-daftar-radiologi-ri`: tabel polos tanpa
 * kartu pembungkus (pembungkusnya sudah disediakan tab Pelayanan Penunjang).
 *
 * TIDAK menampilkan nominal tagihan — itu ranah Administrasi/Kasir. Di EMR yang
 * dibutuhkan hanya: sudah dikirim atau belum, tindakannya apa, dan siapa dokternya.
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
                DB::raw("(SELECT string_agg(a.accdoc_desc) FROM rstxn_okacts t JOIN rsmst_accdocs a ON a.accdoc_id = t.accdoc_id WHERE t.ok_reg = o.ok_reg) AS tindakan_desc"),
            )
            ->where('o.status_rjri', 'RI')
            ->where('o.ref_no', $this->riHdrNo)
            ->orderByDesc('o.ok_reg')
            ->get()
            ->map(fn($transaksiOk) => (array) $transaksiOk)
            ->toArray();
    }
};
?>

<div>
    <table class="w-full text-base text-left text-muted table-auto">
        <thead class="text-sm text-body uppercase bg-surface-soft">
            <tr>
                <th class="px-4 py-3 text-sm font-medium text-muted uppercase dark:text-gray-400">Tgl Operasi</th>
                <th class="px-4 py-3 text-sm font-medium text-muted uppercase dark:text-gray-400">Tindakan / Dokter</th>
                <th class="w-40 px-4 py-3 text-sm font-medium text-center text-muted uppercase dark:text-gray-400">Status</th>
            </tr>
        </thead>
        <tbody class="bg-canvas">
            @forelse ($rows as $row)
                @php
                    $statusOk = strtoupper($row['ok_status'] ?? 'A');
                    $statusOk = $statusOk !== '' ? $statusOk : 'A';
                    [$statusText, $statusVariant] = match ($statusOk) {
                        'A' => ['Proses Transaksi', 'warning'],
                        'L' => ['Transaksi Selesai', 'success'],
                        'F' => ['Dibatalkan', 'danger'],
                        default => [$statusOk, 'gray'],
                    };
                @endphp
                {{-- Baris dibatalkan ditandai merah, sama seperti pasien Batal di Pelayanan RJ. --}}
                <tr wire:key="daftar-ok-ri-{{ $row['ok_reg'] }}"
                    class="border-b group {{ $statusOk === 'F' ? 'bg-error/5 dark:bg-red-900/10 border-l-4 border-error' : '' }}">
                    <td class="px-2 py-2 text-sm font-mono text-muted group-hover:bg-surface-soft whitespace-nowrap">
                        {{ $row['ok_date'] ?? '-' }}
                    </td>
                    <td class="px-2 py-2 text-body group-hover:bg-surface-soft">
                        <span class="block">{{ $row['tindakan_desc'] ?: 'Belum ada tindakan' }}</span>
                        @if (!empty($row['diag_desc']))
                            <span class="block text-sm text-muted">
                                Diagnosa pra-op: <span class="font-medium text-amber-700 dark:text-amber-400">{{ $row['diag_desc'] }}</span>
                            </span>
                        @endif
                        <span class="block text-sm text-muted">
                            Operator: {{ $row['operator_name'] ?? '-' }} &middot; Anestesi: {{ $row['anestesi_name'] ?? '-' }}
                        </span>
                    </td>
                    <td class="px-2 py-2 text-center group-hover:bg-surface-soft">
                        <x-badge :variant="$statusVariant">{{ $statusText }}</x-badge>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-6 text-base text-center text-muted-soft">
                        Belum ada pasien dikirim ke kamar operasi
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
