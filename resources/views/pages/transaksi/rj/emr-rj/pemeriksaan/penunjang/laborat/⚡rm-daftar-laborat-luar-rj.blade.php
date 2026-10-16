<?php
// resources/views/pages/transaksi/rj/emr-rj/pemeriksaan/penunjang/laborat/daftar-lab-luar-rj.blade.php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /*
     | Daftar Lab Luar untuk satu RJ — query langsung ke DB
     | (lbtxn_checkupoutdtls JOIN lbtxn_checkuphdrs WHERE ref_no=rjNo AND status_rjri='RJ').
     | Real-time: respon ke event 'lab-luar-rj.updated' (di-dispatch saat order baru).
     */

    public string $rjNo = '';

    public function mount(string $rjNo = ''): void
    {
        $this->rjNo = $rjNo;
    }

    #[On('lab-luar-rj.updated')]
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

        return DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->select(
                'o.labout_dtl', 'o.checkup_no', 'o.labout_desc', 'o.labout_price',
                'o.labout_result', 'o.labout_normal', 'o.pdf_path', 'o.keterangan',
                'h.checkup_date', 'h.checkup_status',
            )
            ->where('h.ref_no', $this->rjNo)
            ->where('h.status_rjri', 'RJ')
            ->where('h.checkup_status', '!=', 'F')
            ->orderByDesc('h.checkup_date')
            ->orderByDesc('o.labout_dtl')
            ->get();
    }
};
?>

<div>
    <x-modul-dokumen.tabel-daftar :kolom="['Tgl Order', 'Pemeriksaan', 'Status' => 'w-24 text-center']">
            @forelse ($this->rows as $r)
                <tbody class="border-b border-hairline dark:border-gray-700">
                <tr class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                    <td class="px-4 py-3 font-mono text-muted dark:text-gray-300 whitespace-nowrap">
                        {{ $r->checkup_date ? \Carbon\Carbon::parse($r->checkup_date)->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-4 py-3 text-body">
                        {{ $r->labout_desc }}
                        @if ($r->labout_result)
                            <p class="text-sm italic text-muted">Catatan: {{ $r->labout_result }}</p>
                        @endif
                        @if ($r->keterangan)
                            <p class="text-sm italic text-amber-700">Keterangan: {{ $r->keterangan }}</p>
                        @endif
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
                <x-modul-dokumen.baris-kosong :kolom="3" pesan="Belum ada data laboratorium luar" />
            @endforelse
        </x-modul-dokumen.tabel-daftar>
</div>
