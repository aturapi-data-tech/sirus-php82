<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /*
     | Display lab luar pasien — cross-kunjungan by reg_no.
     | Sumber: lbtxn_checkupoutdtls JOIN lbtxn_checkuphdrs WHERE reg_no = ?.
     | Status: PENDING = labout_price IS NULL, POSTED = IS NOT NULL.
     | PDF dari lbtxn_checkupoutdtls.pdf_path (1 dtl = 1 PDF).
     */

    public string $regNo = '';

    public function mount(string $regNo = ''): void
    {
        $this->regNo = $regNo;
    }

    #[Computed]
    public function rows()
    {
        if (empty($this->regNo)) {
            return collect();
        }

        return DB::table('lbtxn_checkupoutdtls as o')
            ->join('lbtxn_checkuphdrs as h', 'o.checkup_no', '=', 'h.checkup_no')
            ->leftJoin('rsmst_doctors as d', 'h.dr_id', '=', 'd.dr_id')
            ->select(
                'o.labout_dtl', 'o.checkup_no', 'o.labout_desc', 'o.labout_price', 'o.pdf_path',
                'h.status_rjri', 'h.ref_no', 'h.checkup_date',
                'd.dr_name',
            )
            ->where('h.reg_no', $this->regNo)
            ->orderByDesc('h.checkup_date')
            ->orderByDesc('o.labout_dtl')
            ->get();
    }
};
?>

<div>
    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Hasil Lab Luar</h3>
            <x-badge variant="gray">{{ count($this->rows) }} item</x-badge>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Tgl Order</th>
                        <th class="px-4 py-3">Sumber</th>
                        <th class="px-4 py-3">Pemeriksaan</th>
                        <th class="px-4 py-3">Dokter Pengirim</th>
                        <th class="px-4 py-3 text-right">Tarif</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Hasil PDF</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->rows as $r)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
                                {{ $r->checkup_date ? \Carbon\Carbon::parse($r->checkup_date)->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-badge variant="alternative">{{ $r->status_rjri }}</x-badge>
                                <span class="ml-1 font-mono text-xs text-gray-500">{{ $r->ref_no }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                {{ $r->labout_desc }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $r->dr_name ?? '-' }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($r->labout_price !== null)
                                    Rp {{ number_format($r->labout_price) }}
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($r->pdf_path)
                                    <x-badge variant="success">SELESAI</x-badge>
                                @elseif ($r->labout_price !== null)
                                    <x-badge variant="warning">Menunggu Hasil</x-badge>
                                @else
                                    <x-badge variant="warning">PENDING</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($r->pdf_path)
                                    <a href="{{ asset('storage/' . $r->pdf_path) }}" target="_blank"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-brand-green hover:underline">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Lihat PDF
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">Belum di-upload</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-sm text-center text-gray-400 dark:text-gray-600">
                                Belum ada order lab luar
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
