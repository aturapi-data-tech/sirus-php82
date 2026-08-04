<?php
// Modal worklist Gizi Rawat Inap (/ri/gizi) — wrapper tipis.
//
// Isi modal = komponen Form Penilaian Gizi EMR yang SAMA dengan tab
// EMR RI → Penilaian → Gizi (rm-penilaian-gizi-ri-actions): satu form,
// dua pintu. Wrapper hanya menyiapkan identitas pasien untuk header
// (query ringan tanpa CLOB) lalu meneruskan event open/save ke komponen.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?string $riHdrNo = null;
    public array $identitas = [];

    #[On('gizi-ri-entri.open')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }

        $this->riHdrNo = $riHdrNo;

        $row = DB::table('rsview_rihdrs')
            ->selectRaw("reg_no, reg_name, bangsal_name, room_name, to_char(entry_date,'dd/mm/yyyy hh24:mi:ss') as entry_date_display")
            ->where('rihdr_no', $riHdrNo)
            ->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $this->identitas = [
            'regNo' => $row->reg_no,
            'regName' => $row->reg_name,
            'bangsal' => $row->bangsal_name,
            'room' => $row->room_name,
            'masuk' => $row->entry_date_display,
        ];

        // Muat data pasien ke komponen form (komponen yang sama dgn tab EMR)
        $this->dispatch('open-rm-penilaian-gizi-ri', $riHdrNo);
        $this->dispatch('open-modal', name: 'gizi-ri-entri');
    }

    public function simpan(): void
    {
        // Save dieksekusi komponen form; setelah tersimpan komponen itu
        // dispatch refresh-after-ri.saved → list worklist ikut segar.
        $this->dispatch('save-rm-penilaian-gizi-ri');
    }
};
?>

<div>
    <x-modal name="gizi-ri-entri" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-4rem)]">

            {{-- HEADER --}}
            <div class="px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold text-xl text-ink dark:text-gray-100">
                            Penilaian Gizi — {{ $identitas['regName'] ?? '-' }}
                        </h2>
                        <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                            No. RM {{ $identitas['regNo'] ?? '-' }}
                            · {{ $identitas['bangsal'] ?? '-' }} / {{ $identitas['room'] ?? '-' }}
                            · Masuk {{ $identitas['masuk'] ?? '-' }}
                        </p>
                    </div>
                    <x-icon-button color="gray" type="button"
                        x-on:click="$dispatch('close-modal', { name: 'gizi-ri-entri' })">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- FORM PENILAIAN GIZI — komponen yang sama dgn tab EMR RI --}}
            <div class="flex-1 px-6 py-5 overflow-y-auto">
                <livewire:pages::transaksi.ri.emr-ri.penilaian-ri.gizi-ri.rm-penilaian-gizi-ri-actions
                    wire:key="gizi-worklist-form" />
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">
                    <x-outline-button type="button"
                        x-on:click="$dispatch('close-modal', { name: 'gizi-ri-entri' })">
                        Tutup
                    </x-outline-button>
                    <x-primary-button type="button" wire:click="simpan" wire:loading.attr="disabled">
                        Simpan Penilaian Gizi
                    </x-primary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
