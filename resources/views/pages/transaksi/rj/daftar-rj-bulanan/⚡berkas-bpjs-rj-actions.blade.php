<?php
// resources/views/pages/transaksi/rj/daftar-rj-bulanan/⚡berkas-bpjs-rj-actions.blade.php
//
// Sibling action component — modal preview Berkas BPJS untuk satu RJ.
// Listen event 'berkas-bpjs.open' → load list rstxn_rjuploadbpjses untuk
// rj_no terpilih, normalize ke 5 slot (SEP/GROUPING/RM/SKDP/LAIN-LAIN),
// tampilkan di modal dengan tombol Lihat per file.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?int $berkasRjNo = null;
    public array $berkasFiles = [];

    #[On('berkas-bpjs.open')]
    public function open(int $rjNo): void
    {
        $this->berkasRjNo = $rjNo;
        $rows = DB::table('rstxn_rjuploadbpjses')
            ->select('seq_file', 'uploadbpjs', 'jenis_file')
            ->where('rj_no', $rjNo)
            ->orderBy('seq_file')
            ->get();

        $labels = [1 => 'SEP', 2 => 'GROUPING', 3 => 'REKAM MEDIS', 4 => 'SKDP', 5 => 'LAIN-LAIN'];
        $bySlot = [];
        foreach ([1, 2, 3, 4, 5] as $slot) {
            $bySlot[$slot] = ['label' => $labels[$slot], 'file' => null];
        }
        foreach ($rows as $r) {
            if (isset($bySlot[$r->seq_file])) {
                $bySlot[$r->seq_file]['file'] = $r->uploadbpjs;
            } else {
                $bySlot[$r->seq_file] = ['label' => 'LAIN-LAIN (#' . $r->seq_file . ')', 'file' => $r->uploadbpjs];
            }
        }
        $this->berkasFiles = $bySlot;
        $this->dispatch('open-modal', name: 'berkas-bpjs-modal');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'berkas-bpjs-modal');
        $this->berkasRjNo = null;
        $this->berkasFiles = [];
    }
};
?>

<div>
    <x-modal name="berkas-bpjs-modal" size="2xl" focusable>
        <div>
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Berkas BPJS
                        </h2>
                        <p class="text-xs text-gray-500">No. RJ:
                            <span class="font-mono font-medium">{{ $berkasRjNo ?? '-' }}</span>
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            <div class="px-6 py-5">
                <table class="w-full text-sm">
                    <thead class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-3 py-2 text-left">Slot</th>
                            <th class="px-3 py-2 text-left">Jenis Berkas</th>
                            <th class="px-3 py-2 text-left">File</th>
                            <th class="px-3 py-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($berkasFiles as $slot => $info)
                            <tr>
                                <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $slot }}</td>
                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">
                                    {{ $info['label'] ?? '-' }}
                                </td>
                                <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {{ $info['file'] ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-center">
                                    @if (!empty($info['file']))
                                        <a href="{{ url('files/bpjs/' . $info['file']) }}" target="_blank"
                                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 hover:bg-brand-green/20">
                                            Lihat
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-8 text-sm text-center text-gray-400">
                                    Tidak ada berkas BPJS
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-2 px-6 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
