<?php
// resources/views/pages/transaksi/ri/emr-ri/pemeriksaan-ri/penunjang/laborat/rm-daftar-laborat-luar-ri.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /*
     | Daftar Lab Luar untuk satu RI — query langsung ke DB
     | (lbtxn_checkupoutdtls JOIN lbtxn_checkuphdrs WHERE ref_no=riHdrNo AND status_rjri='RI').
     | Real-time: respon ke event 'lab-luar-ri.updated'.
     */

    public ?string $riHdrNo = null;

    public function mount(?string $riHdrNo = null): void
    {
        $this->riHdrNo = $riHdrNo;
    }

    #[On('lab-luar-ri.updated')]
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

        return DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->leftJoin('rsmst_doctors as d', 'h.dr_id', '=', 'd.dr_id')
            ->select(
                'o.labout_dtl', 'o.checkup_no', 'o.labout_desc', 'o.labout_price',
                'o.labout_result', 'o.pdf_path', 'o.keterangan',
                'h.checkup_date', 'h.checkup_status',
                'd.dr_name',
            )
            ->where('h.ref_no', $this->riHdrNo)
            ->where('h.status_rjri', 'RI')
            ->where('h.checkup_status', '!=', 'F')
            ->orderByDesc('h.checkup_date')
            ->orderByDesc('o.labout_dtl')
            ->get();
    }
};
?>

<div>
    <x-modul-dokumen.tabel-daftar :kolom="['Tgl Order', 'Pemeriksaan', 'Dokter Pengirim', 'Status' => 'w-24 text-center']">
            @forelse ($this->rows as $r)
                <tbody class="border-b border-hairline dark:border-gray-700">
                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                    <td class="px-4 py-3 font-mono text-muted dark:text-gray-300 whitespace-nowrap">
                        {{ $r->checkup_date ? \Carbon\Carbon::parse($r->checkup_date)->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-4 py-3 text-body">
                        {{ $r->labout_desc }}
                        @if ($r->labout_result)
                            <p class="text-xs italic text-muted">Catatan: {{ $r->labout_result }}</p>
                        @endif
                        @if ($r->keterangan)
                            <p class="text-xs italic text-amber-700">Keterangan: {{ $r->keterangan }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-body">
                        {{ $r->dr_name ?? '-' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if ($r->checkup_status === 'H')
                            <x-badge variant="success">Selesai</x-badge>
                        @elseif ($r->checkup_status === 'C')
                            <x-badge variant="info">Menunggu Hasil</x-badge>
                        @else
                            <x-badge variant="warning">Terdaftar</x-badge>
                        @endif
                    </td>
                </tr>
                </tbody>
            @empty
                <x-modul-dokumen.baris-kosong :kolom="4" pesan="Belum ada data laboratorium luar" />
            @endforelse
        </x-modul-dokumen.tabel-daftar>
</div>
