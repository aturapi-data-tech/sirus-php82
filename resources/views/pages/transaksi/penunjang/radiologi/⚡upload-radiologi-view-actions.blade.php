<?php
// resources/views/pages/transaksi/penunjang/radiologi/⚡upload-radiologi-view-actions.blade.php
//
// Sibling action component — VIEW FILE RADIOLOGI (foto / hasil bacaan) di dalam modal.
// Baca file di iframe (pola radiologi-display RM), bukan buka tab baru.
// Listener event dari halaman utama:
//   - radiologi.view.open  (file, title)
//
// File di private disk → route('files.show'); legacy full-path → asset('storage/..').

use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component {
    public string $viewFilePDF = '';
    public string $viewFileTitle = '';

    #[On('radiologi.view.open')]
    public function open(?string $file, string $title = 'File Radiologi'): void
    {
        $url = $this->resolveFileUrl($file);
        if (!$url) {
            $this->dispatch('toast', type: 'error', message: 'File tidak ditemukan di server.');
            return;
        }
        $this->viewFilePDF = $url;
        $this->viewFileTitle = $title;
        $this->dispatch('open-modal', name: 'view-radiologi-pdf');
    }

    public function closeView(): void
    {
        $this->reset(['viewFilePDF', 'viewFileTitle']);
        $this->dispatch('close-modal', name: 'view-radiologi-pdf');
    }

    private function resolveFileUrl(?string $name): ?string
    {
        if (empty($name)) {
            return null;
        }
        return str_contains($name, '/') ? asset('storage/' . $name) : route('files.show', ['path' => 'mount/penunjang/radiologi/' . $name]);
    }
};
?>

<div>
    <x-modal name="view-radiologi-pdf" size="full" height="full" focusable>
        <div class="flex flex-col h-[calc(100vh-4rem)]" wire:key="view-radiologi-{{ $viewFilePDF }}">
            {{-- HEADER --}}
            <x-modul-dokumen.header :judul="$viewFileTitle ?: 'Lihat File Radiologi'" ikon="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" tutup="closeView" />
            @if ($viewFilePDF)
                <div class="flex flex-wrap items-center gap-2 px-6 py-2 border-b border-hairline dark:border-gray-700">
                    <a href="{{ $viewFilePDF }}" target="_blank" rel="noopener"
                        class="px-3 py-1.5 text-xs font-medium text-body bg-surface-soft rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                        Buka di Tab Baru
                    </a>
                </div>
            @endif

            {{-- BODY — iframe streaming via route files.show --}}
            <div class="flex-1 p-2 bg-surface-soft dark:bg-gray-900">
                @if ($viewFilePDF)
                    <iframe src="{{ $viewFilePDF }}" class="w-full h-full border-0" type="application/pdf"></iframe>
                @endif
            </div>
        </div>
    </x-modal>
</div>
