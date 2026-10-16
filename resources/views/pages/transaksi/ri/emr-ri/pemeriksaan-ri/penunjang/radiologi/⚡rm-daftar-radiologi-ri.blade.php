<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/radiologi/rm-daftar-radiologi-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component {
    /*
     | Daftar Radiologi untuk satu RI — query langsung ke DB
     | (rstxn_riradiologs JOIN rsmst_radiologis WHERE rihdr_no=riHdrNo).
     | Group per waktu_entry (1 kiriman = banyak item dengan timestamp identik).
     | Real-time: respon ke event 'radiologi-order-terkirim'.
     */

    public ?string $riHdrNo = null;

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
    }

    #[On('radiologi-order-terkirim')]
    public function refresh(): void
    {
        // Computed property auto re-evaluate.
    }

    #[Computed]
    public function rows()
    {
        if (empty($this->riHdrNo)) {
            return collect();
        }

        $items = DB::table('rstxn_riradiologs as r')
            ->join('rsmst_radiologis as m', 'r.rad_id', '=', 'm.rad_id')
            ->select('r.rirad_no', 'r.rihdr_no', 'r.waktu_entry', 'm.rad_desc', 'r.dr_pengirim', 'r.keterangan', 'r.cito_status')
            ->where('r.rihdr_no', $this->riHdrNo)
            ->orderByDesc('r.waktu_entry')
            ->orderBy('r.rirad_no')
            ->get();

        return $items
            ->groupBy(fn($pemeriksaan) => $pemeriksaan->waktu_entry ? Carbon::parse($pemeriksaan->waktu_entry)->format('YmdHis') : 'null')
            ->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'rirad_no' => $first->rirad_no,
                    'rihdr_no' => $first->rihdr_no,
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
