<?php

use Livewire\Component;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\ProcessFailedException;

new class extends Component {
    public string $shareServer = '//172.8.8.12/rad_path/';
    public string $mountPoint = '/var/www/html/sirus-php82/storage/app/private/mount/penunjang/radiologi';
    // (RAD Share — foto + hasil bacaan share folder ini, di-mount dari //172.8.8.12/rad_path/)
    public string $shareServerUpload = '//172.8.8.12/upload_path/';
    public string $mountPointUpload = '/var/www/html/sirus-php82/storage/app/private/mount/penunjang/emr/uploadHasilPenunjang';
    public string $shareServerLab = '//172.8.8.12/lab_path/';
    public string $mountPointLab = '/var/www/html/sirus-php82/storage/app/private/mount/penunjang/lab-luar';
    public string $shareServerBpjs = '//172.8.8.12/bpjs_path/';
    public string $mountPointBpjs = '/var/www/html/sirus-php82/storage/app/private/mount/bpjs';
    // Berkas Scan Luar dari Modul Dokumen RJ/UGD/RI — folder sama dengan App\Support\BerkasLuar::FOLDER_MOUNT.
    public string $shareServerDokumen = '//172.8.8.12/dokumen_path/';
    public string $mountPointDokumen = '/var/www/html/sirus-php82/storage/app/private/mount/dokumen/emr/uploadBerkasLuar';
    public string $statusMessage = '';
    public string $statusMessageUpload = '';
    public string $statusMessageLab = '';
    public string $statusMessageBpjs = '';
    public string $statusMessageDokumen = '';
    public bool $isMounted = false;
    public bool $isMountedUpload = false;
    public bool $isMountedLab = false;
    public bool $isMountedBpjs = false;
    public bool $isMountedDokumen = false;

    /* poppler-utils (pdfunite) — dipakai fitur Gabung Berkas BPJS */
    public bool $pdfuniteInstalled = false;
    public string $pdfuniteVersion = '';

    /* User proses PHP yang menjalankan sudo — dipakai baris sudoers di kartu Setup Server */
    public string $userProsesPhp = '';

    public function mount(): void
    {
        $this->userProsesPhp = function_exists('posix_geteuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data')
            : 'www-data';

        $this->checkMounted();
        $this->checkMountedUpload();
        $this->checkMountedLab();
        $this->checkMountedBpjs();
        $this->checkMountedDokumen();
        $this->checkPdfunite();
    }

    public function mountShare(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServer, $this->mountPoint]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessage = '✓ Mount berhasil di: ' . $this->mountPoint;
            $this->dispatch('toast', type: 'success', message: $this->statusMessage);
        } catch (ProcessFailedException $e) {
            $this->statusMessage = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessage);
        } catch (\Throwable $e) {
            $this->statusMessage = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessage);
        }

        $this->checkMounted();
    }

    public function unmountShare(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPoint]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessage = '✓ Unmount berhasil dari: ' . $this->mountPoint;
            $this->dispatch('toast', type: 'success', message: $this->statusMessage);
        } catch (ProcessFailedException $e) {
            $this->statusMessage = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessage);
        } catch (\Throwable $e) {
            $this->statusMessage = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessage);
        }

        $this->checkMounted();
    }

    public function checkMounted(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPoint]);

            $process->setTimeout(2);
            $process->run();

            $this->isMounted = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMounted = false;
        } catch (\Throwable) {
            $this->isMounted = false;
        }
    }

    /**
     * Setup awal: bikin folder mount point bila belum ada.
     * Idempotent — aman dipanggil berulang. Recursive = true biar parent path ikut dibuat.
     */
    public function createMountPoint(): void
    {
        $this->createDirectorySafe($this->mountPoint, 'RAD');
    }

    public function createMountPointUpload(): void
    {
        $this->createDirectorySafe($this->mountPointUpload, 'EMR');
    }

    public function createMountPointLab(): void
    {
        $this->createDirectorySafe($this->mountPointLab, 'Lab');
    }

    public function createMountPointBpjs(): void
    {
        $this->createDirectorySafe($this->mountPointBpjs, 'BPJS');
    }

    public function createMountPointDokumen(): void
    {
        $this->createDirectorySafe($this->mountPointDokumen, 'Berkas Scan Luar');
    }

    private function createDirectorySafe(string $path, string $label): void
    {
        try {
            if (is_dir($path)) {
                $this->dispatch('toast', type: 'info', message: 'Folder ' . $label . ' sudah ada: ' . $path);
                return;
            }
            if (mkdir($path, 0775, true)) {
                $this->dispatch('toast', type: 'success', message: '✓ Folder ' . $label . ' dibuat: ' . $path);
            } else {
                $this->dispatch('toast', type: 'error', message: '✗ Gagal buat folder ' . $label . '.');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: '✗ Error: ' . $e->getMessage());
        }
    }

    public function mountShareUpload(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServerUpload, $this->mountPointUpload]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageUpload = '✓ Mount berhasil di: ' . $this->mountPointUpload;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageUpload);
        } catch (ProcessFailedException $e) {
            $this->statusMessageUpload = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageUpload);
        } catch (\Throwable $e) {
            $this->statusMessageUpload = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageUpload);
        }

        $this->checkMountedUpload();
    }

    public function unmountShareUpload(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPointUpload]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageUpload = '✓ Unmount berhasil dari: ' . $this->mountPointUpload;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageUpload);
        } catch (ProcessFailedException $e) {
            $this->statusMessageUpload = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageUpload);
        } catch (\Throwable $e) {
            $this->statusMessageUpload = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageUpload);
        }

        $this->checkMountedUpload();
    }

    public function checkMountedUpload(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPointUpload]);

            $process->setTimeout(2);
            $process->run();

            $this->isMountedUpload = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMountedUpload = false;
        } catch (\Throwable) {
            $this->isMountedUpload = false;
        }
    }

    public function mountShareLab(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServerLab, $this->mountPointLab]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageLab = '✓ Mount berhasil di: ' . $this->mountPointLab;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageLab);
        } catch (ProcessFailedException $e) {
            $this->statusMessageLab = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageLab);
        } catch (\Throwable $e) {
            $this->statusMessageLab = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageLab);
        }

        $this->checkMountedLab();
    }

    public function unmountShareLab(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPointLab]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageLab = '✓ Unmount berhasil dari: ' . $this->mountPointLab;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageLab);
        } catch (ProcessFailedException $e) {
            $this->statusMessageLab = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageLab);
        } catch (\Throwable $e) {
            $this->statusMessageLab = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageLab);
        }

        $this->checkMountedLab();
    }

    public function checkMountedLab(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPointLab]);

            $process->setTimeout(2);
            $process->run();

            $this->isMountedLab = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMountedLab = false;
        } catch (\Throwable) {
            $this->isMountedLab = false;
        }
    }

    public function mountShareBpjs(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServerBpjs, $this->mountPointBpjs]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageBpjs = '✓ Mount berhasil di: ' . $this->mountPointBpjs;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageBpjs);
        } catch (ProcessFailedException $e) {
            $this->statusMessageBpjs = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageBpjs);
        } catch (\Throwable $e) {
            $this->statusMessageBpjs = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageBpjs);
        }

        $this->checkMountedBpjs();
    }

    public function unmountShareBpjs(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPointBpjs]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageBpjs = '✓ Unmount berhasil dari: ' . $this->mountPointBpjs;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageBpjs);
        } catch (ProcessFailedException $e) {
            $this->statusMessageBpjs = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageBpjs);
        } catch (\Throwable $e) {
            $this->statusMessageBpjs = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageBpjs);
        }

        $this->checkMountedBpjs();
    }

    public function checkMountedBpjs(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPointBpjs]);

            $process->setTimeout(2);
            $process->run();

            $this->isMountedBpjs = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMountedBpjs = false;
        } catch (\Throwable) {
            $this->isMountedBpjs = false;
        }
    }

    public function mountShareDokumen(): void
    {
        $process = new Process(['sudo', '/usr/bin/mount', '-t', 'cifs', $this->shareServerDokumen, $this->mountPointDokumen]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageDokumen = '✓ Mount berhasil di: ' . $this->mountPointDokumen;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageDokumen);
        } catch (ProcessFailedException $e) {
            $this->statusMessageDokumen = '✗ Mount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageDokumen);
        } catch (\Throwable $e) {
            $this->statusMessageDokumen = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageDokumen);
        }

        $this->checkMountedDokumen();
    }

    public function unmountShareDokumen(): void
    {
        $process = new Process(['sudo', '/usr/bin/umount', $this->mountPointDokumen]);

        try {
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->statusMessageDokumen = '✓ Unmount berhasil dari: ' . $this->mountPointDokumen;
            $this->dispatch('toast', type: 'success', message: $this->statusMessageDokumen);
        } catch (ProcessFailedException $e) {
            $this->statusMessageDokumen = '✗ Unmount gagal: ' . trim($process->getErrorOutput());
            $this->dispatch('toast', type: 'error', message: $this->statusMessageDokumen);
        } catch (\Throwable $e) {
            $this->statusMessageDokumen = '✗ Error: ' . $e->getMessage();
            $this->dispatch('toast', type: 'error', message: $this->statusMessageDokumen);
        }

        $this->checkMountedDokumen();
    }

    public function checkMountedDokumen(): void
    {
        try {
            $process = new Process(['sudo', '/usr/bin/mountpoint', '-q', $this->mountPointDokumen]);

            $process->setTimeout(2);
            $process->run();

            $this->isMountedDokumen = $process->getExitCode() === 0;
        } catch (ProcessTimedOutException) {
            $this->isMountedDokumen = false;
        } catch (\Throwable) {
            $this->isMountedDokumen = false;
        }
    }

    /* ===============================
     | BULK ACTIONS — Mount All / Unmount All
     =============================== */
    public function mountAll(): void
    {
        // Mount semua share yang belum ter-mount. Skip yg sudah, biar idempotent.
        if (!$this->isMounted)        $this->mountShare();
        if (!$this->isMountedUpload)  $this->mountShareUpload();
        if (!$this->isMountedLab)     $this->mountShareLab();
        if (!$this->isMountedBpjs)    $this->mountShareBpjs();
        if (!$this->isMountedDokumen) $this->mountShareDokumen();

        $this->dispatch('toast', type: 'success', message: '✓ Mount All selesai. Cek status tiap share.');
    }

    public function unmountAll(): void
    {
        // Unmount semua share yang sedang ter-mount.
        if ($this->isMounted)        $this->unmountShare();
        if ($this->isMountedUpload)  $this->unmountShareUpload();
        if ($this->isMountedLab)     $this->unmountShareLab();
        if ($this->isMountedBpjs)    $this->unmountShareBpjs();
        if ($this->isMountedDokumen) $this->unmountShareDokumen();

        $this->dispatch('toast', type: 'success', message: '✓ Unmount All selesai.');
    }

    /* ===============================
     | POPPLER-UTILS (pdfunite) — install & cek
     | Dipakai fitur Gabung Berkas BPJS (RJ).
     =============================== */
    public function checkPdfunite(): void
    {
        try {
            $process = new Process(['/usr/bin/which', 'pdfunite']);
            $process->setTimeout(3);
            $process->run();
            $this->pdfuniteInstalled = $process->getExitCode() === 0 && trim($process->getOutput()) !== '';

            if ($this->pdfuniteInstalled) {
                $verProc = Process::fromShellCommandline('pdfunite -v 2>&1 | head -1');
                $verProc->setTimeout(3);
                $verProc->run();
                $this->pdfuniteVersion = trim($verProc->getOutput()) ?: 'installed';
            } else {
                $this->pdfuniteVersion = '';
            }
        } catch (\Throwable) {
            $this->pdfuniteInstalled = false;
            $this->pdfuniteVersion = '';
        }
    }

    public function installPdfunite(): void
    {
        // sudo + DEBIAN_FRONTEND=noninteractive supaya apt tidak prompt apa-apa.
        $process = Process::fromShellCommandline(
            'sudo DEBIAN_FRONTEND=noninteractive /usr/bin/apt-get install -y poppler-utils 2>&1'
        );

        try {
            $process->setTimeout(180);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->checkPdfunite();
            $this->dispatch('toast', type: 'success',
                message: '✓ poppler-utils terpasang. ' . ($this->pdfuniteVersion ?: ''));
        } catch (ProcessFailedException $e) {
            $this->dispatch('toast', type: 'error',
                message: '✗ Install gagal: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: '✗ Error: ' . $e->getMessage());
        }
    }
};
?>

<div>
    {{-- PAGE HEADER --}}
    <x-page-title
        title="Mounting Control"
        subtitle="Manajemen mount/unmount share folder jaringan (CIFS/SMB)" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 pt-2 pb-6">

            {{-- BULK ACTIONS — Mount All / Unmount All --}}
            <div class="flex flex-wrap items-center justify-end gap-2 mb-4">
                <x-primary-button type="button" wire:click="mountAll" wire:loading.attr="disabled"
                    wire:target="mountAll" class="whitespace-nowrap" title="Mount semua share yang belum tersambung">
                    <span wire:loading.remove wire:target="mountAll" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                        Mount All
                    </span>
                    <span wire:loading wire:target="mountAll" class="flex items-center gap-1">
                        <x-loading /> Mounting...
                    </span>
                </x-primary-button>

                <x-warning-button type="button" wire:click="unmountAll" wire:loading.attr="disabled"
                    wire:target="unmountAll" class="whitespace-nowrap" title="Unmount semua share yang sedang tersambung">
                    <span wire:loading.remove wire:target="unmountAll" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                        Unmount All
                    </span>
                    <span wire:loading wire:target="unmountAll" class="flex items-center gap-1">
                        <x-loading /> Unmounting...
                    </span>
                </x-warning-button>

                {{-- Install poppler-utils (pdfunite) — dipakai fitur Gabung Berkas BPJS --}}
                @if ($pdfuniteInstalled)
                    <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-md bg-brand-green/10 text-brand-green border border-brand-green/20 whitespace-nowrap"
                        title="{{ $pdfuniteVersion }}">
                        ✓ poppler-utils
                    </span>
                @else
                    <div class="flex flex-col items-end gap-1">
                        <x-info-button type="button" wire:click="installPdfunite"
                            wire:loading.attr="disabled" wire:target="installPdfunite"
                            wire:confirm="Jalankan: sudo apt-get install -y poppler-utils ?"
                            class="whitespace-nowrap"
                            title="Install paket poppler-utils (pdfunite) — perlu untuk fitur Gabung Berkas BPJS">
                            <span wire:loading.remove wire:target="installPdfunite" class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                                </svg>
                                Install poppler-utils
                            </span>
                            <span wire:loading wire:target="installPdfunite" class="flex items-center gap-1">
                                <x-loading /> Installing...
                            </span>
                        </x-info-button>
                        <code class="px-1.5 py-0.5 text-[11px] font-mono text-muted dark:text-gray-400 bg-surface-soft dark:bg-gray-800 rounded">
                            sudo apt install poppler-utils
                        </code>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- =================================================== --}}
                {{-- LEFT (2/3): 5 SHARE SECTIONS                          --}}
                {{-- =================================================== --}}
                <div class="lg:col-span-2 space-y-6">

            {{-- ================================================================ --}}
            {{-- SECTION: RAD SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                {{-- HEADER BAR (di dalam card, bukan sticky) --}}
                <div class="px-5 py-3 bg-surface-soft border-b border-hairline dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-body uppercase dark:text-gray-200">RAD Share</span>
                            @if ($isMounted)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMounted" wire:loading.attr="disabled"
                                wire:target="checkMounted" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMounted">Cek Status</span>
                                <span wire:loading wire:target="checkMounted"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPoint" wire:loading.attr="disabled"
                                wire:target="createMountPoint" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPoint">Buat Folder</span>
                                <span wire:loading wire:target="createMountPoint"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShare" wire:loading.attr="disabled"
                                wire:target="mountShare" :disabled="$isMounted" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShare">Mount</span>
                                <span wire:loading wire:target="mountShare"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShare" wire:loading.attr="disabled"
                                wire:target="unmountShare" :disabled="!$isMounted" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShare">Unmount</span>
                                <span wire:loading wire:target="unmountShare"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                {{-- BODY: Form --}}
                <div class="p-5 space-y-4">
                    <div>
                        <x-input-label value="Share Server" />
                        <x-text-input wire:model.live="shareServer" class="block w-full mt-1 font-mono"
                            placeholder="//host/share" />
                    </div>
                    <div>
                        <x-input-label value="Mount Point" />
                        <x-text-input wire:model.live="mountPoint" class="block w-full mt-1 font-mono"
                            placeholder="/mnt/target" />
                    </div>

                    @if ($statusMessage !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessage, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessage, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessage }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SECTION: EMR SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3 bg-surface-soft border-b border-hairline dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-body uppercase dark:text-gray-200">EMR Share</span>
                            @if ($isMountedUpload)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMountedUpload" wire:loading.attr="disabled"
                                wire:target="checkMountedUpload" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMountedUpload">Cek Status</span>
                                <span wire:loading wire:target="checkMountedUpload"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPointUpload" wire:loading.attr="disabled"
                                wire:target="createMountPointUpload" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPointUpload">Buat Folder</span>
                                <span wire:loading wire:target="createMountPointUpload"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShareUpload" wire:loading.attr="disabled"
                                wire:target="mountShareUpload" :disabled="$isMountedUpload" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShareUpload">Mount</span>
                                <span wire:loading wire:target="mountShareUpload"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShareUpload" wire:loading.attr="disabled"
                                wire:target="unmountShareUpload" :disabled="!$isMountedUpload" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShareUpload">Unmount</span>
                                <span wire:loading wire:target="unmountShareUpload"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <x-input-label value="Share Server (EMR)" />
                        <x-text-input wire:model.live="shareServerUpload" class="block w-full mt-1 font-mono"
                            placeholder="//host/share" />
                    </div>
                    <div>
                        <x-input-label value="Mount Point (EMR)" />
                        <x-text-input wire:model.live="mountPointUpload" class="block w-full mt-1 font-mono"
                            placeholder="/mnt/target" />
                    </div>

                    @if ($statusMessageUpload !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessageUpload, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessageUpload, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessageUpload }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SECTION: LAB SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3 bg-surface-soft border-b border-hairline dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-body uppercase dark:text-gray-200">Lab Share</span>
                            @if ($isMountedLab)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMountedLab" wire:loading.attr="disabled"
                                wire:target="checkMountedLab" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMountedLab">Cek Status</span>
                                <span wire:loading wire:target="checkMountedLab"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPointLab" wire:loading.attr="disabled"
                                wire:target="createMountPointLab" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPointLab">Buat Folder</span>
                                <span wire:loading wire:target="createMountPointLab"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShareLab" wire:loading.attr="disabled"
                                wire:target="mountShareLab" :disabled="$isMountedLab" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShareLab">Mount</span>
                                <span wire:loading wire:target="mountShareLab"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShareLab" wire:loading.attr="disabled"
                                wire:target="unmountShareLab" :disabled="!$isMountedLab" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShareLab">Unmount</span>
                                <span wire:loading wire:target="unmountShareLab"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <x-input-label value="Share Server (Lab)" />
                        <x-text-input wire:model.live="shareServerLab" class="block w-full mt-1 font-mono"
                            placeholder="//host/share" />
                    </div>
                    <div>
                        <x-input-label value="Mount Point (Lab)" />
                        <x-text-input wire:model.live="mountPointLab" class="block w-full mt-1 font-mono"
                            placeholder="/mnt/target" />
                    </div>

                    @if ($statusMessageLab !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessageLab, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessageLab, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessageLab }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SECTION: BPJS SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3 bg-surface-soft border-b border-hairline dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-body uppercase dark:text-gray-200">BPJS Share</span>
                            @if ($isMountedBpjs)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMountedBpjs" wire:loading.attr="disabled"
                                wire:target="checkMountedBpjs" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMountedBpjs">Cek Status</span>
                                <span wire:loading wire:target="checkMountedBpjs"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPointBpjs" wire:loading.attr="disabled"
                                wire:target="createMountPointBpjs" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPointBpjs">Buat Folder</span>
                                <span wire:loading wire:target="createMountPointBpjs"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShareBpjs" wire:loading.attr="disabled"
                                wire:target="mountShareBpjs" :disabled="$isMountedBpjs" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShareBpjs">Mount</span>
                                <span wire:loading wire:target="mountShareBpjs"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShareBpjs" wire:loading.attr="disabled"
                                wire:target="unmountShareBpjs" :disabled="!$isMountedBpjs" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShareBpjs">Unmount</span>
                                <span wire:loading wire:target="unmountShareBpjs"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <x-input-label value="Share Server (BPJS)" />
                        <x-text-input wire:model.live="shareServerBpjs" class="block w-full mt-1 font-mono"
                            placeholder="//host/share" />
                    </div>
                    <div>
                        <x-input-label value="Mount Point (BPJS)" />
                        <x-text-input wire:model.live="mountPointBpjs" class="block w-full mt-1 font-mono"
                            placeholder="/mnt/target" />
                    </div>

                    @if ($statusMessageBpjs !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessageBpjs, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessageBpjs, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessageBpjs }}</p>
                        </div>
                    @endif
                </div>
            </div>


            {{-- ================================================================ --}}
            {{-- SECTION: BERKAS SCAN LUAR SHARE --}}
            {{-- ================================================================ --}}
            <div class="bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
                <div class="px-5 py-3 bg-surface-soft border-b border-hairline dark:bg-gray-800/60 dark:border-gray-700">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-body uppercase dark:text-gray-200">Berkas Scan Luar Share</span>
                            @if ($isMountedDokumen)
                                <x-badge variant="success">Terhubung</x-badge>
                            @else
                                <x-badge variant="danger">Tidak Terhubung</x-badge>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 ml-auto">
                            <x-ghost-button type="button" wire:click="checkMountedDokumen" wire:loading.attr="disabled"
                                wire:target="checkMountedDokumen" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="checkMountedDokumen">Cek Status</span>
                                <span wire:loading wire:target="checkMountedDokumen"><x-loading /></span>
                            </x-ghost-button>
                            <x-secondary-button type="button" wire:click="createMountPointDokumen" wire:loading.attr="disabled"
                                wire:target="createMountPointDokumen" class="whitespace-nowrap" title="Buat folder mount point (idempotent)">
                                <span wire:loading.remove wire:target="createMountPointDokumen">Buat Folder</span>
                                <span wire:loading wire:target="createMountPointDokumen"><x-loading /></span>
                            </x-secondary-button>
                            <x-primary-button type="button" wire:click="mountShareDokumen" wire:loading.attr="disabled"
                                wire:target="mountShareDokumen" :disabled="$isMountedDokumen" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="mountShareDokumen">Mount</span>
                                <span wire:loading wire:target="mountShareDokumen"><x-loading /></span>
                            </x-primary-button>
                            <x-warning-button type="button" wire:click="unmountShareDokumen" wire:loading.attr="disabled"
                                wire:target="unmountShareDokumen" :disabled="!$isMountedDokumen" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="unmountShareDokumen">Unmount</span>
                                <span wire:loading wire:target="unmountShareDokumen"><x-loading /></span>
                            </x-warning-button>
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <div>
                        <x-input-label value="Share Server (Berkas Scan Luar)" />
                        <x-text-input wire:model.live="shareServerDokumen" class="block w-full mt-1 font-mono"
                            placeholder="//host/share" />
                    </div>
                    <div>
                        <x-input-label value="Mount Point (Berkas Scan Luar)" />
                        <x-text-input wire:model.live="mountPointDokumen" class="block w-full mt-1 font-mono"
                            placeholder="/mnt/target" />
                    </div>

                    @if ($statusMessageDokumen !== '')
                        <div @class([
                            'p-3 rounded-lg border text-xs font-medium',
                            'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-700 dark:text-green-300' => str_starts_with($statusMessageDokumen, '✓'),
                            'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-700 dark:text-red-300' => str_starts_with($statusMessageDokumen, '✗'),
                        ])>
                            <p class="break-all">{{ $statusMessageDokumen }}</p>
                        </div>
                    @endif
                </div>
            </div>

                </div> {{-- /lg:col-span-2 (left side: 5 share sections) --}}

                {{-- =================================================== --}}
                {{-- RIGHT (1/3): VISUAL ARSITEKTUR MOUNTING (sticky)     --}}
                {{-- =================================================== --}}
                <div class="lg:col-span-1 lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">

            {{-- ================================================================ --}}
            {{-- VISUAL: ARSITEKTUR MOUNTING (compact, vertikal — fit kolom 1/3)   --}}
            {{-- ================================================================ --}}
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-blue-200 dark:border-blue-700 bg-blue-100/50 dark:bg-blue-900/30 backdrop-blur">
                    <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">
                        Konsep Mounting File Server
                    </h3>
                    <p class="text-[11px] text-blue-700 dark:text-blue-300">
                        Alur Upload → Sync external → Mount read.
                    </p>
                </div>

                <div class="p-4 space-y-3 bg-canvas dark:bg-gray-900">

                    {{-- 3 STEP — selalu vertikal, compact --}}
                    {{-- Step 1: Laravel Upload --}}
                    <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-white text-[10px] font-bold">1</span>
                            <p class="text-xs font-bold text-blue-900 dark:text-blue-200">Laravel Upload (write)</p>
                        </div>
                        <p class="text-[11px] text-blue-800 dark:text-blue-300 leading-relaxed">User upload via UI → cache lokal:</p>
                        <code class="block mt-1 px-2 py-1 text-[10px] font-mono bg-canvas/80 dark:bg-gray-900/60 rounded break-all text-blue-900 dark:text-blue-300">storage/app/private/<strong>upload</strong>/...</code>
                    </div>

                    {{-- Arrow ↓ sync --}}
                    <div class="flex items-center justify-center gap-2 text-purple-500 dark:text-purple-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        <span class="text-[10px] font-semibold uppercase tracking-wide">sync</span>
                    </div>

                    {{-- Step 2: External Sync --}}
                    <div class="p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700/50">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-500 text-white text-[10px] font-bold">2</span>
                            <p class="text-xs font-bold text-purple-900 dark:text-purple-200">External Sync (cron/rsync)</p>
                        </div>
                        <p class="text-[11px] text-purple-800 dark:text-purple-300 leading-relaxed">Program di luar Laravel pindahkan file:</p>
                        <code class="block mt-1 px-2 py-1 text-[10px] font-mono bg-canvas/80 dark:bg-gray-900/60 rounded break-all text-purple-900 dark:text-purple-300">upload/ → \\172.8.8.12\xxx_path</code>
                    </div>

                    {{-- Arrow ↓ CIFS mount --}}
                    <div class="flex items-center justify-center gap-2 text-emerald-500 dark:text-emerald-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        <span class="text-[10px] font-semibold uppercase tracking-wide">CIFS mount</span>
                    </div>

                    {{-- Step 3: Mount Read --}}
                    <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700/50">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500 text-white text-[10px] font-bold">3</span>
                            <p class="text-xs font-bold text-emerald-900 dark:text-emerald-200">Mount Read (canonical)</p>
                        </div>
                        <p class="text-[11px] text-emerald-800 dark:text-emerald-300 leading-relaxed">Laravel baca dari mount, fallback upload:</p>
                        <code class="block mt-1 px-2 py-1 text-[10px] font-mono bg-canvas/80 dark:bg-gray-900/60 rounded break-all text-emerald-900 dark:text-emerald-300">storage/app/private/<strong>mount</strong>/...</code>
                    </div>

                    {{-- Mapping per share — STACKED CARDS bukan table (cocok kolom sempit) --}}
                    <div class="pt-2 mt-3 border-t border-hairline dark:border-gray-700">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted dark:text-gray-400 mb-2">Mapping per Share</p>

                        @foreach ([
                            [
                                'name' => 'Radiologi',
                                'desc' => 'Saat petugas radiologi upload foto rontgen/USG/CT-scan dan hasil bacaan dokter radiolog.',
                                'upload' => 'upload/penunjang/radiologi',
                                'server' => '\\\\172.8.8.12\\rad_path',
                                'mount' => 'mount/penunjang/radiologi',
                            ],
                            [
                                'name' => 'Hasil Penunjang dari EMR (RJ/UGD/RI)',
                                'desc' => 'Saat perawat/dokter di EMR rawat jalan/UGD/rawat inap meng-attach hasil pemeriksaan penunjang dari luar (mis. EKG, hasil rujukan).',
                                'upload' => 'upload/penunjang/emr/uploadHasilPenunjang',
                                'server' => '\\\\172.8.8.12\\upload_path',
                                'mount' => 'mount/penunjang/emr/uploadHasilPenunjang',
                            ],
                            [
                                'name' => 'Laboratorium Luar',
                                'desc' => 'Saat petugas lab menerima hasil dari laboratorium rujukan luar dan upload PDF/JPG hasilnya.',
                                'upload' => 'upload/penunjang/lab-luar',
                                'server' => '\\\\172.8.8.12\\lab_path',
                                'mount' => 'mount/penunjang/lab-luar',
                            ],
                            [
                                'name' => 'Berkas Klaim BPJS',
                                'desc' => 'Saat casemix/admin upload berkas pengajuan klaim BPJS — SEP, Grouping, Rekam Medis, SKDP, dan dokumen lain-lain.',
                                'upload' => 'upload/bpjs',
                                'server' => '\\\\172.8.8.12\\bpjs_path',
                                'mount' => 'mount/bpjs',
                            ],
                            [
                                'name' => 'Berkas Scan Luar (Modul Dokumen)',
                                'desc' => 'Saat petugas upload berkas dari luar (hasil scan) di Modul Dokumen RJ/UGD/RI — surat rujukan, hasil pemeriksaan luar, resume RS lain, kartu identitas.',
                                'upload' => 'upload/dokumen/emr/uploadBerkasLuar',
                                'server' => '\\\\172.8.8.12\\dokumen_path',
                                'mount' => 'mount/dokumen/emr/uploadBerkasLuar',
                            ],
                        ] as $share)
                            <div class="mb-2 p-2.5 rounded-lg bg-canvas dark:bg-gray-900 border border-hairline dark:border-gray-700">
                                <p class="text-xs font-bold text-ink dark:text-gray-100 mb-0.5">{{ $share['name'] }}</p>
                                <p class="text-[10px] text-muted dark:text-gray-400 leading-snug mb-2">{{ $share['desc'] }}</p>
                                <div class="space-y-1 text-[10px] font-mono break-all">
                                    <div class="flex items-start gap-1.5">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500 mt-1.5 shrink-0"></span>
                                        <span class="text-muted dark:text-gray-400">{{ $share['upload'] }}</span>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-500 mt-1.5 shrink-0"></span>
                                        <span class="text-muted dark:text-gray-400">{{ $share['server'] }}</span>
                                    </div>
                                    <div class="flex items-start gap-1.5">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5 shrink-0"></span>
                                        <span class="text-muted dark:text-gray-400">{{ $share['mount'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <div class="flex flex-wrap gap-3 text-[10px] text-muted dark:text-gray-400 mt-2">
                            <span class="inline-flex items-center gap-1"><span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-500"></span>upload</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block w-1.5 h-1.5 rounded-full bg-purple-500"></span>file server</span>
                            <span class="inline-flex items-center gap-1"><span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>mount</span>
                        </div>
                    </div>

                    {{-- Note: kenapa pisah upload & mount --}}
                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50">
                        <p class="text-[11px] font-semibold text-amber-800 dark:text-amber-300 mb-1">💡 Kenapa pisah upload &amp; mount?</p>
                        <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">File medis bisa besar → simpan di NAS/SAN supaya disk Laravel tidak penuh + backup terpusat. <code class="px-1 rounded bg-amber-100 dark:bg-amber-900/40 font-mono">upload/</code> = cache sementara.</p>
                    </div>

                    {{-- Note: Exception User TTD --}}
                    <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-700/50">
                        <p class="text-[11px] font-semibold text-rose-800 dark:text-rose-300 mb-1">⚠️ Exception: User TTD</p>
                        <p class="text-[11px] text-rose-800 dark:text-rose-300 leading-relaxed mb-1.5">
                            Tanda tangan karyawan <strong>tidak ikut mount/share</strong>. Tetap di <code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">storage/app/public/UserTtd/</code>.
                        </p>
                        <p class="text-[11px] text-rose-800 dark:text-rose-300 leading-relaxed mb-1.5">
                            <strong>Alasan:</strong> TTD dipakai sebagai <code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">&lt;img&gt;</code> di 7+ template PDF (RM, eresep, lab/rad, GC). DomPDF resolve relative ke <code class="px-1 rounded bg-rose-100 dark:bg-rose-900/40 font-mono">public/</code> — pindah ke share = refactor lintas template.
                        </p>
                        <p class="text-[11px] text-rose-800 dark:text-rose-300 leading-relaxed">
                            <strong>Konsekuensi:</strong> backup TTD prosedur terpisah; ukuran kecil per file, total tidak signifikan.
                        </p>
                    </div>
                </div>
            </div>

            {{-- ================================================================ --}}
            {{-- SETUP SERVER — sekali per server, bila Mount gagal karena sudo   --}}
            {{-- ================================================================ --}}
            <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-blue-200 dark:border-blue-700 bg-blue-100/50 dark:bg-blue-900/30">
                    <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">
                        Setup Server (sekali per server)
                    </h3>
                    <p class="text-[11px] text-blue-700 dark:text-blue-300">
                        Jalankan di terminal server Ubuntu bila Mount gagal. User PHP halaman ini:
                        <code class="px-1 rounded bg-blue-100 dark:bg-blue-900/40 font-mono">{{ $userProsesPhp }}</code>
                    </p>
                </div>

                <div class="p-4 space-y-3 bg-canvas dark:bg-gray-900">
                    @foreach ([
                        [
                            'judul' => 'Pasang paket',
                            'ket' => 'cifs-utils untuk mount share Windows/SMB, poppler-utils untuk Gabung Berkas BPJS.',
                            'kode' => 'sudo apt install -y cifs-utils poppler-utils',
                        ],
                        [
                            'judul' => 'Izinkan user PHP menjalankan mount tanpa password',
                            'ket' => 'Buka file sudoers baru — selalu lewat visudo, salah ketik di sudoers bisa merusak sudo.',
                            'kode' => 'sudo visudo -f /etc/sudoers.d/sirus-mount',
                        ],
                        [
                            'judul' => 'Isi file tersebut dengan satu baris ini, simpan, lalu kunci izinnya',
                            'ket' => 'Baris pertama masuk ke visudo; baris kedua dijalankan di terminal.',
                            'kode' => $userProsesPhp . ' ALL=(root) NOPASSWD: /usr/bin/mount, /usr/bin/umount, /usr/bin/mountpoint, /usr/bin/apt-get' . "\n" . 'sudo chmod 440 /etc/sudoers.d/sirus-mount',
                        ],
                        [
                            'judul' => 'Uji dari terminal',
                            'ket' => 'Hasil "is not a mountpoint" / "is a mountpoint" = izin sudah benar. "password is required" = user atau path perintah belum cocok (cek: which mount umount mountpoint).',
                            'kode' => 'sudo -u ' . $userProsesPhp . ' sudo -n /usr/bin/mountpoint ' . $mountPoint,
                        ],
                        [
                            'judul' => 'Kembali ke halaman ini',
                            'ket' => 'Tekan Buat Folder di tiap kartu, lalu Mount All. Status berubah menjadi Terhubung.',
                            'kode' => '',
                        ],
                    ] as $nomor => $langkah)
                        <div class="p-3 rounded-lg bg-surface-soft dark:bg-gray-800/60 border border-hairline dark:border-gray-700">
                            <div class="flex items-start gap-2 mb-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-500 text-white text-[10px] font-bold shrink-0">{{ $nomor + 1 }}</span>
                                <p class="text-xs font-bold text-ink dark:text-gray-100">{{ $langkah['judul'] }}</p>
                            </div>
                            <p class="text-[11px] text-muted dark:text-gray-400 leading-relaxed">{{ $langkah['ket'] }}</p>
                            @if ($langkah['kode'] !== '')
                                <code class="block mt-1.5 px-2 py-1.5 text-[10px] font-mono whitespace-pre-wrap break-all rounded bg-gray-900 text-gray-100 dark:bg-gray-800">{{ $langkah['kode'] }}</code>
                            @endif
                        </div>
                    @endforeach

                    {{-- Pesan error → penyebab --}}
                    <div class="pt-2 border-t border-hairline dark:border-gray-700">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted dark:text-gray-400 mb-2">Pesan error → penyebab</p>
                        @foreach ([
                            ['a terminal is required / a password is required', 'Langkah 2–3 belum dibuat, atau nama user di sudoers tidak sama dengan user PHP di atas.'],
                            ['unknown filesystem type \'cifs\' / wrong fs type', 'cifs-utils belum terpasang (langkah 1).'],
                            ['mount point does not exist / No such file', 'Folder belum ada — tekan Buat Folder dulu.'],
                            ['mount error(13): Permission denied', 'Share 172.8.8.12 menolak tanpa kredensial — samakan setelan dengan server lama (/etc/fstab, file kredensial SMB).'],
                            ['mount error(112) / Host is down', 'File server mati atau port 445 diblok firewall.'],
                            ['Status Tidak Terhubung padahal Mount berhasil', 'Tekan Cek Status; bila tetap, sudoers belum mengizinkan /usr/bin/mountpoint.'],
                        ] as [$pesanError, $penyebab])
                            <div class="mb-2 p-2.5 rounded-lg border border-hairline dark:border-gray-700">
                                <p class="text-[10px] font-mono font-semibold text-red-700 dark:text-red-300 break-all">{{ $pesanError }}</p>
                                <p class="text-[11px] text-muted dark:text-gray-400 leading-snug mt-0.5">{{ $penyebab }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/50">
                        <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">
                            Project pindah folder/server? Mount Point default di atas ditulis tetap
                            (<code class="px-1 rounded bg-amber-100 dark:bg-amber-900/40 font-mono">/var/www/html/sirus-php82/...</code>) —
                            ubah di berkas halaman ini, lepas mount lama (<code class="px-1 rounded bg-amber-100 dark:bg-amber-900/40 font-mono">sudo umount &lt;path lama&gt;</code>), lalu Mount All lagi.
                        </p>
                    </div>
                </div>
            </div>

                </div> {{-- /lg:col-span-1 (right side: visual arsitektur) --}}
            </div> {{-- /grid (2:1 split) --}}

        </div>
    </div>
</div>
