<?php
// resources/views/pages/transaksi/rj/emr-rj/pemeriksaan/penunjang/laborat/rm-daftar-laborat-rj.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /*
     | Daftar Laboratorium (internal) untuk satu RJ — query langsung ke DB
     | (lbtxn_checkupdtls JOIN lbtxn_checkuphdrs WHERE ref_no=rjNo AND status_rjri='RJ').
     | Real-time: respon ke event 'laborat-order-terkirim' (di-dispatch saat order baru).
     */

    public string $rjNo = '';

    public function mount(string $rjNo = ''): void
    {
        $this->rjNo = $rjNo;
    }

    #[On('laborat-order-terkirim')]
    public function refresh(): void
    {
        // Computed property auto re-evaluate; method ini cuma trigger render.
    }

    #[Computed]
    public function rows()
    {
        if (empty($this->rjNo)) {
            return collect();
        }

        $items = DB::table('lbtxn_checkuphdrs as h')
            ->join('lbtxn_checkupdtls as d', 'h.checkup_no', '=', 'd.checkup_no')
            ->join('lbmst_clabitems as c', 'd.clabitem_id', '=', 'c.clabitem_id')
            ->select(
                'h.checkup_no', 'h.checkup_date', 'h.checkup_status', 'h.cito_status',
                'c.clabitem_desc',
            )
            ->where('h.ref_no', $this->rjNo)
            ->where('h.status_rjri', 'RJ')
            ->whereNotNull('d.price')
            ->orderByDesc('h.checkup_date')
            ->orderBy('d.checkup_dtl')
            ->get();

        return $items->groupBy('checkup_no')->map(function ($group) {
            $first = $group->first();
            return (object) [
                'checkup_no' => $first->checkup_no,
                'checkup_date' => $first->checkup_date,
                'checkup_status' => $first->checkup_status,
                'cito_status' => $first->cito_status,
                'items' => $group->pluck('clabitem_desc')->implode(', '),
            ];
        })->values();
    }
};
?>

<div>
    <x-modul-dokumen.tabel-daftar :kolom="['Tgl Lab', 'Pemeriksaan Lab', 'Status' => 'w-24 text-center']">
            @forelse ($this->rows as $orderLab)
                <tbody class="border-b border-hairline dark:border-gray-700">
                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                    <td class="px-4 py-3 font-mono text-muted dark:text-gray-300 whitespace-nowrap">
                        {{ $orderLab->checkup_date ? \Carbon\Carbon::parse($orderLab->checkup_date)->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-4 py-3 text-body">
                        @if ($orderLab->cito_status === '1')
                            <x-badge variant="danger" class="mr-1 font-bold">CITO</x-badge>
                        @endif
                        {{ $orderLab->items }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if ($orderLab->checkup_status === 'H')
                            <x-badge variant="success">Selesai</x-badge>
                        @elseif ($orderLab->checkup_status === 'C')
                            <x-badge variant="info">Proses</x-badge>
                        @else
                            <x-badge variant="warning">Terdaftar</x-badge>
                        @endif
                    </td>
                </tr>
                </tbody>
            @empty
                <x-modul-dokumen.baris-kosong :kolom="3" pesan="Belum ada data laboratorium" />
            @endforelse
        </x-modul-dokumen.tabel-daftar>
</div>
