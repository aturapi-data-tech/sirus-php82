<?php
// resources/views/pages/transaksi/rj/emr-rj/pemeriksaan/penunjang/radiologi/rm-daftar-radiologi-rj.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    /*
     | Daftar Radiologi untuk satu RJ — query langsung ke DB
     | (rstxn_rjrads JOIN rsmst_radiologis WHERE rj_no=rjNo).
     | Group per waktu_entry (1 kiriman = banyak item dengan timestamp identik).
     | Real-time: respon ke event 'radiologi-order-terkirim'.
     */

    public string $rjNo = '';

    public function mount(string $rjNo = ''): void
    {
        $this->rjNo = $rjNo;
    }

    #[On('radiologi-order-terkirim')]
    public function refresh(): void
    {
        // Trigger render via computed property re-evaluation.
    }

    #[Computed]
    public function rows()
    {
        if (empty($this->rjNo)) {
            return collect();
        }

        $items = DB::table('rstxn_rjrads as r')
            ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
            ->select('r.rad_dtl', 'r.rj_no', 'r.waktu_entry', 'm.rad_desc', 'r.dr_pengirim', 'r.keterangan', 'r.cito_status')
            ->where('r.rj_no', $this->rjNo)
            ->orderByDesc('r.waktu_entry')
            ->orderBy('r.rad_dtl')
            ->get();

        return $items
            ->groupBy(fn($pemeriksaan) => $pemeriksaan->waktu_entry ? Carbon::parse($pemeriksaan->waktu_entry)->format('YmdHis') : 'null')
            ->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'rj_no' => $first->rj_no,
                    'waktu_entry' => $first->waktu_entry,
                    'dr_pengirim' => $first->dr_pengirim,
                    'cito_status' => $first->cito_status,
                    'items' => $group->map(fn($pemeriksaan) => $pemeriksaan->rad_desc . ($pemeriksaan->keterangan ? ' (' . $pemeriksaan->keterangan . ')' : ''))->implode(', '),
                ];
            })
            ->values();
    }
};
?>

<div>
    <x-modul-dokumen.tabel-daftar :kolom="['Tgl Rad', 'Pemeriksaan Rad', 'Dokter Pengirim', 'Status' => 'w-24 text-center']">
            @forelse ($this->rows as $orderRadiologi)
                <tbody class="border-b border-hairline dark:border-gray-700">
                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                    <td class="px-4 py-3 font-mono text-muted dark:text-gray-300 whitespace-nowrap">
                        {{ $orderRadiologi->waktu_entry ? \Carbon\Carbon::parse($orderRadiologi->waktu_entry)->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-4 py-3 text-body">
                        @if ($orderRadiologi->cito_status === '1')
                            <x-badge variant="danger" class="mr-1 font-bold">CITO</x-badge>
                        @endif
                        {{ $orderRadiologi->items }}
                    </td>
                    <td class="px-4 py-3 text-body">
                        {{ $orderRadiologi->dr_pengirim ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-center text-muted-soft">
                        -
                    </td>
                </tr>
                </tbody>
            @empty
                <x-modul-dokumen.baris-kosong :kolom="4" pesan="Belum ada data radiologi" />
            @endforelse
        </x-modul-dokumen.tabel-daftar>
</div>
