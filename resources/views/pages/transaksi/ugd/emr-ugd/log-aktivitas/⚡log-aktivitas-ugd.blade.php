<?php
// resources/views/pages/transaksi/ugd/emr-ugd/log-aktivitas/log-aktivitas-ugd.blade.php
// Modul Log Aktivitas UGD — audit terpadu admin + rekam medis (listen: emr-ugd.log-aktivitas.open)

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ugd\EmrUGDTrait;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;

new class extends Component {
    use EmrUGDTrait, WithRenderVersioningTrait;

    public ?int $rjNo = null;
    public array $adminLogs = [];
    public string $filterCat = ''; // '' = semua | 'ADMIN' | 'MR'

    public array $renderVersions = [];
    protected array $renderAreas = ['modal-log-aktivitas-ugd'];

    public function mount(): void
    {
        $this->registerAreas(['modal-log-aktivitas-ugd']);
    }

    #[On('emr-ugd.log-aktivitas.open')]
    public function open(int $rjNo): void
    {
        if (empty($rjNo)) {
            return;
        }

        // Log Aktivitas hanya untuk Manager ke atas (leveling README: level 3-4).
        if (!auth()->user()->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Manager ke atas yang dapat melihat Log Aktivitas.');
            return;
        }

        $this->rjNo = $rjNo;
        $this->filterCat = '';
        $this->loadLogs($rjNo);

        $this->incrementVersion('modal-log-aktivitas-ugd');
        $this->dispatch('open-modal', name: 'log-aktivitas-ugd'); // ← WAJIB ada ini
    }

    public function setFilter(string $cat): void
    {
        $this->filterCat = $cat;
        if ($this->rjNo) {
            $this->loadLogs($this->rjNo);
        }
    }

    public function closeModal(): void
    {
        // Kosongkan penanda supaya isi modal (guard @if) benar-benar dihapus saat tutup.
        $this->rjNo = null;
        $this->adminLogs = [];
        $this->dispatch('close-modal', name: 'log-aktivitas-ugd');
    }

    private function loadLogs(int $rjNo): void
    {
        $data = $this->findDataUGD($rjNo);
        $logs = $data['AdministrasiUGD']['userLogs'] ?? [];

        // Entri lama tanpa flag dianggap 'ADMIN'.
        if ($this->filterCat !== '') {
            $logs = array_values(array_filter(
                $logs,
                fn($l) => ($l['userLogCat'] ?? 'ADMIN') === $this->filterCat,
            ));
        }

        usort($logs, function ($a, $b) {
            $ta = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $a['userLogDate'] ?? '01/01/1970 00:00:00')->getTimestamp();
            $tb = \Carbon\Carbon::createFromFormat('d/m/Y H:i:s', $b['userLogDate'] ?? '01/01/1970 00:00:00')->getTimestamp();
            return $tb <=> $ta;
        });
        $this->adminLogs = $logs;
    }
};
?>

<div>
    <x-modal name="log-aktivitas-ugd" size="full" height="full" focusable>
        {{-- Isi modal hanya di-mount saat ada pasien: tertutup = nol komponen, buka = mount sekali,
             tutup = dihapus tanpa mount ulang (closeModal mengosongkan rjNo). --}}
        @if ($rjNo)
        <div class="flex flex-col min-h-0"
            wire:key="{{ $this->renderKey('modal-log-aktivitas-ugd', [$rjNo ?? 'new']) }}">

            {{-- HEADER --}}
            <x-modul-dokumen.header judul="Log Aktivitas" ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" jalur="UGD">
            </x-modul-dokumen.header>

            {{-- DISPLAY PASIEN --}}
            <div class="px-6 pt-3">
                <livewire:pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo="$rjNo"
                    wire:key="log-aktivitas-display-pasien-ugd-header-{{ $rjNo }}" />
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-hairline dark:border-gray-700">
                        <div class="flex items-center gap-1">
                            @php
                                $tabs = ['' => 'Semua', 'ADMIN' => 'Administrasi', 'MR' => 'Rekam Medis'];
                            @endphp
                            @foreach ($tabs as $val => $label)
                                <button type="button" wire:click="setFilter('{{ $val }}')"
                                    class="rounded-lg px-2.5 py-1 text-xs font-medium transition
                                    {{ $filterCat === $val
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-surface-soft text-muted hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <x-badge variant="gray">{{ count($adminLogs) }} entri</x-badge>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-surface-card dark:bg-gray-800">
                                <tr class="text-xs font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                                    <th class="px-4 py-3 whitespace-nowrap border-b border-hairline dark:border-gray-700">Tanggal</th>
                                    <th class="px-4 py-3 whitespace-nowrap border-b border-hairline dark:border-gray-700">Kategori</th>
                                    <th class="px-4 py-3 whitespace-nowrap border-b border-hairline dark:border-gray-700">User</th>
                                    <th class="px-4 py-3 whitespace-nowrap border-b border-hairline dark:border-gray-700">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($adminLogs as $log)
                                    @php $cat = $log['userLogCat'] ?? 'ADMIN'; @endphp
                                    <tr wire:key="log-aktivitas-ugd-{{ $loop->index }}" class="border-b border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                        <td class="px-4 py-3 font-mono text-xs text-muted whitespace-nowrap">{{ $log['userLogDate'] ?? '-' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $cat === 'MR'
                                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                                    : 'bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400' }}">
                                                {{ $cat === 'MR' ? 'Rekam Medis' : 'Administrasi' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-muted dark:text-gray-400 whitespace-nowrap">{{ $log['userLog'] ?? '-' }}</td>
                                        <td class="px-4 py-3 text-ink dark:text-gray-200">{{ $log['userLogDesc'] ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-muted-soft">
                                            Belum ada log aktivitas
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-3">
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
        @endif
    </x-modal>
</div>
