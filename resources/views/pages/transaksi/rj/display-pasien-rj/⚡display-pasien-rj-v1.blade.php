<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;

new class extends Component {
    use MasterPasienTrait;

    public array $dataPasien = [];
    public string $regNo = '';
    public bool $isLoading = false;
    public ?string $errorMessage = null;

    /**
     * Load patient data by registration number
     */
    public function loadPasien(string $regNo): void
    {
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $result = $this->getMasterPasien($regNo);

            $this->dataPasien = $result;
            $this->regNo = $regNo;

            $this->dispatch('pasien-loaded', regNo: $regNo);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal memuat data pasien: ' . $e->getMessage();
        } finally {
            $this->isLoading = false;
        }
    }
};

?>

<div class="space-y-4">
    <!-- Loading State -->
    @if ($isLoading)
        <div class="flex items-center justify-center p-8">
            <div class="w-8 h-8 border-4 border-blue-500 rounded-full border-t-transparent animate-spin"></div>
            <span class="ml-3 text-gray-600 dark:text-gray-400">Memuat data pasien...</span>
        </div>
    @endif

    <!-- Error Message -->
    @if ($errorMessage)
        <div class="p-4 border-l-4 border-red-500 rounded-r-lg bg-red-50 dark:bg-red-900/50">
            <div class="flex">
                <svg class="w-5 h-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="ml-3 text-sm text-red-700 dark:text-red-300">
                    {{ $errorMessage }}
                </p>
            </div>
        </div>
    @endif

    <!-- Patient Data Display -->
    @if (!empty($dataPasien['pasien']['regNo']) && $dataPasien['pasien']['regNo'] !== '-')
        <div class="bg-white rounded-lg shadow-sm dark:bg-gray-800">
            <!-- Header with actions -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Data Pasien: {{ $dataPasien['pasien']['regName'] ?? '-' }}
                    <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                        ({{ $dataPasien['pasien']['regNo'] ?? '-' }})
                    </span>
                </h3>
                <div class="flex space-x-2">
                    <x-secondary-button wire:click="refreshPasien" wire:loading.attr="disabled">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </x-secondary-button>
                    <x-secondary-button wire:click="clearCache" class="text-yellow-600 hover:text-yellow-700">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Clear Cache
                    </x-secondary-button>
                </div>
            </div>

            <!-- Patient data display -->
            <div class="p-4">
                @include('partials.patient-data-display', ['data' => $dataPasien])
            </div>
        </div>
    @elseif(!$isLoading && !$errorMessage)
        <div class="p-8 text-center rounded-lg bg-yellow-50 dark:bg-yellow-900/50">
            <p class="text-yellow-800 dark:text-yellow-200">
                Belum ada data pasien. Silakan cari pasien terlebih dahulu.
            </p>
        </div>
    @endif
</div>
