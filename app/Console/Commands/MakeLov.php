<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeLov extends Command
{
    protected $signature = 'make:lov
        {path : Contoh: product/lov-product}
        {--force : Overwrite jika file sudah ada}';

    protected $description = 'Create a Livewire 4 SFC LOV component under resources/views/livewire/lov/*';

    public function handle(): int
    {
        $path = trim($this->argument('path'), '/');
        $segments = array_values(array_filter(explode('/', $path)));

        if (count($segments) === 0) {
            $this->error("Path tidak valid.");
            return self::FAILURE;
        }

        $fileName = array_pop($segments);
        $lovFolder = base_path('resources/views/livewire/lov/' . implode('/', $segments));
        $fullPath = $lovFolder . '/' . $fileName . '.blade.php';

        if (!File::exists($lovFolder)) {
            File::makeDirectory($lovFolder, 0755, true);
        }

        if (File::exists($fullPath) && !$this->option('force')) {
            $this->error("LOV already exists: {$fullPath}");
            $this->line("Gunakan --force untuk overwrite.");
            return self::FAILURE;
        }

        File::put($fullPath, $this->stubTemplate());

        $this->info("Created: {$fullPath}");
        $this->line("Use it as: <livewire:lov." . str_replace('/', '.', $path) . " />");

        return self::SUCCESS;
    }

    protected function stubTemplate(): string
    {
        return <<<'BLADE'
<?php

use Livewire\Component;

/**
 * LOV Base (SFC - Livewire 4)
 * - Reusable
 * - Keyboard navigation (wire:keydown.*)
 * - Scroll mengikuti highlight (lov-scroll)
 * - Emits selected item to parent (dispatch: lov.selected)
 *
 * Use:
 * <livewire:lov.product.lov-product target="formEntryProduct" />
 *
 * Parent:
 * #[On('lov.selected')]
 * public function handleLovSelected(string $target, array $payload) {}
 */
new class extends Component
{
    /** Target untuk membedakan LOV dipakai di form mana */
    public string $target = 'default';

    /** UI */
    public string $label = 'Cari';
    public string $placeholder = 'Ketik untuk mencari...';

    /** State */
    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    public function updatedSearch(): void
    {
        $keyword = trim($this->search);

        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        /**
         * TODO: Ganti query sesuai LOV masing-masing.
         * options harus array seperti:
         * [
         *   ['label' => 'Nama', 'hint' => 'Kode • Harga', ...payload...],
         *   ...
         * ]
         */
        $this->options = [];

        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) return;

        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) return;

        $this->selectedIndex--;
        if ($this->selectedIndex < 0) $this->selectedIndex = count($this->options) - 1;

        $this->emitScroll();
    }

    public function choose(int $index): void
    {
        if (!isset($this->options[$index])) return;

        $payload = $this->options[$index];

        // event standar untuk semua LOV
        $this->dispatch('lov.selected', target: $this->target, payload: $payload);

        $this->resetLov();
    }

    public function chooseHighlighted(): void
    {
        $this->choose($this->selectedIndex);
    }

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    protected function emitScroll(): void
    {
        // id komponen unik agar event tidak nyasar kalau ada banyak LOV di halaman
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        <x-text-input
            type="text"
            class="block w-full"
            :placeholder="$placeholder"
            wire:model.live.debounce.250ms="search"
            wire:keydown.escape.prevent="resetLov"
            wire:keydown.arrow-down.prevent="selectNext"
            wire:keydown.arrow-up.prevent="selectPrevious"
            wire:keydown.enter.prevent="chooseHighlighted"
        />

        @if ($isOpen)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-option-{{ $index }}" x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $option['label'] ?? 'Item' }}
                                </div>

                                @if (!empty($option['hint']))
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $option['hint'] }}
                                    </div>
                                @endif
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>

                @if (mb_strlen(trim($search)) >= 2 && count($options) === 0)
                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                        Data tidak ditemukan.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-lov.dropdown>
BLADE;
    }
}
