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
    <x-modul-dokumen.tabel-daftar :kolom="['Tgl Operasi', 'Tindakan / Dokter', 'Status' => 'w-40 text-center']">
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
                <tbody wire:key="daftar-ok-ri-{{ $row['ok_reg'] }}" class="border-b border-hairline dark:border-gray-700">
                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/60 {{ $statusOk === 'F' ? 'bg-error/5 dark:bg-red-900/10 border-l-4 border-error' : '' }}">
                    <td class="px-4 py-3 font-mono text-muted dark:text-gray-300 whitespace-nowrap">
                        {{ $row['ok_date'] ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-body">
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
                    <td class="px-4 py-3 text-center">
                        <x-badge :variant="$statusVariant">{{ $statusText }}</x-badge>
                    </td>
                </tr>
                </tbody>
            @empty
                <x-modul-dokumen.baris-kosong :kolom="3" pesan="Belum ada pasien dikirim ke kamar operasi" />
            @endforelse
        </x-modul-dokumen.tabel-daftar>
</div>
