<?php
// resources/views/pages/database-monitor/monitor-server/monitor-server.blade.php
// Monitor Server — kesehatan worker PHP-FPM + log yang dipakai melacak "server tidak bisa diakses":
// timeout Apache (AH01075), peringatan PHP-FPM, slowlog (stack trace request lambat), error Laravel.
// Log milik root dibaca lewat sudo tail dengan argumen persis (lihat tab Setup Server).

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;

new class extends Component {
    private const BERKAS_LOG_APACHE = '/var/log/apache2/sirus-ssl-error.log';
    private const BERKAS_LOG_FPM = '/var/log/php8.4-fpm.log';
    private const BERKAS_SLOWLOG = '/var/log/php8.4-fpm-sirus-slow.log';
    private const JUMLAH_BARIS_SUDO = 3000;
    private const BATAS_BYTE_BACA = 1572864;
    private const BATAS_BYTE_LARAVEL = 4194304;

    public string $tab = 'ringkasan';

    /* Grafik berjalan (opsional) — sampel tetap dikumpulkan tiap poll walau grafik disembunyikan,
       jadi saat dinyalakan riwayat 10 menit terakhir langsung tampil. */
    public bool $grafikBerjalan = false;
    private const JUMLAH_SAMPEL = 60; // 60 × poll 10 detik = 10 menit
    public array $sampelPool = ['jam' => [], 'aktif' => [], 'antrean' => []];

    /* User proses PHP — dipakai baris sudoers di tab Setup Server */
    public string $userProsesPhp = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('Admin'), 403);

        $this->userProsesPhp = function_exists('posix_geteuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data')
            : 'www-data';
    }

    public function pilihTab(string $tab): void
    {
        if (!in_array($tab, ['ringkasan', 'apache', 'fpm', 'slowlog', 'laravel', 'setup'], true)) {
            return;
        }
        $this->tab = $tab;
    }

    public function refresh(): void
    {
        unset($this->statusPool, $this->timeoutApache, $this->logFpm, $this->slowlog, $this->errorLaravel, $this->kesehatan);
    }

    /* Dipanggil wire:poll tab Ringkasan: muat ulang angka + catat satu sampel untuk grafik berjalan. */
    public function ambilSampel(): void
    {
        $this->refresh();
        $statusPool = $this->statusPool;
        if ($statusPool === null) {
            return;
        }
        foreach (['jam' => now()->format('H:i:s'), 'aktif' => $statusPool['aktif'], 'antrean' => $statusPool['antrean']] as $kunci => $nilai) {
            $this->sampelPool[$kunci][] = $nilai;
            $this->sampelPool[$kunci] = array_slice($this->sampelPool[$kunci], -self::JUMLAH_SAMPEL);
        }
        $this->kirimSampel();
    }

    public function updatedGrafikBerjalan(): void
    {
        // Kanvas baru dirender di respons ini — kirim riwayat yang ada supaya grafik langsung terisi.
        $this->kirimSampel();
    }

    private function kirimSampel(): void
    {
        if (!$this->grafikBerjalan) {
            return;
        }
        $this->dispatch('monitor-server-sampel',
            jam: $this->sampelPool['jam'],
            aktif: $this->sampelPool['aktif'],
            antrean: $this->sampelPool['antrean'],
            batas: $this->maxChildren,
        );
    }

    /* pm.max_children pool ini dibaca dari berkas pool.d (milik root tapi bisa dibaca semua user).
       fpm_get_status() tidak memuatnya. null = berkas tak terbaca (mis. bukan server FPM). */
    #[Computed]
    public function maxChildren(): ?int
    {
        $namaPool = $this->statusPool['pool'] ?? null;
        if ($namaPool === null) {
            return null;
        }
        $berkas = '/etc/php/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '/fpm/pool.d/' . $namaPool . '.conf';
        $isi = is_readable($berkas) ? (string) file_get_contents($berkas) : '';
        return preg_match('/^\s*pm\.max_children\s*=\s*(\d+)/m', $isi, $cocok) ? (int) $cocok[1] : null;
    }

    /**
     * Status keseluruhan Normal / Waspada / Bahaya + alasannya. Ambang sama dengan tabel
     * "Tanda-tanda" di tab Ringkasan — ubah keduanya bersamaan.
     * Error Laravel sengaja TIDAK ikut: error aplikasi tak berarti server tak bisa diakses.
     */
    #[Computed]
    public function kesehatan(): array
    {
        $statusPool = $this->statusPool;
        $batas = $this->maxChildren;
        $bahaya = [];
        $waspada = [];

        if ($statusPool !== null) {
            if ($statusPool['antrean'] > 0) {
                $bahaya[] = "Ada {$statusPool['antrean']} request mengantre — semua worker sedang sibuk.";
            }
            if ($batas) {
                $persen = (int) round($statusPool['aktif'] / $batas * 100);
                if ($persen >= 90) {
                    $bahaya[] = "Worker aktif {$statusPool['aktif']} dari {$batas} ({$persen}%) — hampir penuh.";
                } elseif ($persen >= 70) {
                    $waspada[] = "Worker aktif {$statusPool['aktif']} dari {$batas} ({$persen}%).";
                }
            }
            $workerLama = collect($statusPool['workerList'])->filter(fn($worker) => $worker['state'] === 'Running' && $worker['durasiDetik'] >= 15)->count();
            if ($workerLama > 0) {
                $waspada[] = "{$workerLama} worker sedang mengerjakan request lebih dari 15 detik.";
            }
        }

        $timeoutApache = $this->timeoutApache;
        $logFpm = $this->logFpm;
        foreach ([
            ['baru' => $timeoutApache['jumlah15Menit'], 'hariIni' => $timeoutApache['jumlahHariIni'], 'teks' => 'timeout 504 (pengguna gagal membuka halaman)'],
            ['baru' => $logFpm['jumlah15Menit']['maxChildren'], 'hariIni' => $logFpm['jumlahHariIni']['maxChildren'], 'teks' => 'pool mentok max_children'],
            ['baru' => $logFpm['jumlah15Menit']['dimatikan'], 'hariIni' => $logFpm['jumlahHariIni']['dimatikan'], 'teks' => 'worker dimatikan karena timed out'],
        ] as $tanda) {
            if ($tanda['baru'] > 0) {
                $bahaya[] = "{$tanda['baru']}× {$tanda['teks']} dalam 15 menit terakhir.";
            } elseif ($tanda['hariIni'] > 0) {
                $waspada[] = "{$tanda['hariIni']}× {$tanda['teks']} hari ini (bukan 15 menit terakhir).";
            }
        }

        return [
            'level' => $bahaya ? 'bahaya' : ($waspada ? 'waspada' : 'normal'),
            'alasan' => array_merge($bahaya, $waspada),
        ];
    }

    /* Status pool PHP-FPM yang melayani halaman ini (fpm_get_status hanya ada di SAPI fpm). */
    #[Computed]
    public function statusPool(): ?array
    {
        if (!function_exists('fpm_get_status')) {
            return null;
        }
        $status = fpm_get_status();
        if (!is_array($status)) {
            return null;
        }

        $workerList = collect($status['procs'] ?? [])
            ->map(fn($proses) => [
                'pid' => $proses['pid'] ?? 0,
                'state' => $proses['state'] ?? '-',
                'durasiDetik' => round(((int) ($proses['request-duration'] ?? 0)) / 1000000, 1),
                'method' => $proses['request-method'] ?? '-',
                'uri' => $proses['request-uri'] ?? '-',
                'jumlahRequest' => $proses['requests'] ?? 0,
                'memoriMb' => round(((int) ($proses['last-request-memory'] ?? 0)) / 1048576, 1),
            ])
            ->sortByDesc(fn($worker) => $worker['state'] === 'Running' ? 1000000 + $worker['durasiDetik'] : $worker['durasiDetik'])
            ->values()
            ->all();

        return [
            'pool' => $status['pool'] ?? '-',
            'mulai' => isset($status['start-time']) ? Carbon::createFromTimestamp($status['start-time'])->format('d/m/Y H:i') : '-',
            'aktif' => $status['active-processes'] ?? 0,
            'idle' => $status['idle-processes'] ?? 0,
            'total' => $status['total-processes'] ?? 0,
            'aktifTertinggi' => $status['max-active-processes'] ?? 0,
            'mentokMaxChildren' => $status['max-children-reached'] ?? 0,
            'antrean' => $status['listen-queue'] ?? 0,
            'antreanTertinggi' => $status['max-listen-queue'] ?? 0,
            'requestLambat' => $status['slow-requests'] ?? 0,
            'requestDiterima' => $status['accepted-conn'] ?? 0,
            'workerList' => $workerList,
        ];
    }

    /* AH01075 = Apache menunggu worker FPM sampai timeout → pengguna melihat 504 Gateway Timeout. */
    #[Computed]
    public function timeoutApache(): array
    {
        $bacaan = $this->bacaEkorBerkas(self::BERKAS_LOG_APACHE, self::BATAS_BYTE_BACA);
        $hariIni = Carbon::today();
        $batasBaru = now()->subMinutes(15);
        $rows = [];
        $jumlahHariIni = 0;
        $jumlah15Menit = 0;
        $perHalaman = [];

        foreach ($bacaan['baris'] as $baris) {
            if (!str_contains($baris, 'AH01075')) {
                continue;
            }
            if (!preg_match('/^\[\w{3} (\w{3})\s+(\d{1,2}) ([\d:]{8})[.\d]* (\d{4})\]/', $baris, $cocokWaktu)) {
                continue;
            }
            $waktu = Carbon::createFromFormat('M j H:i:s Y', "{$cocokWaktu[1]} {$cocokWaktu[2]} {$cocokWaktu[3]} {$cocokWaktu[4]}");
            preg_match('/\[client ([\d.]+):\d+\]/', $baris, $cocokClient);
            preg_match('/, referer: (\S+)\s*$/', $baris, $cocokReferer);

            $halaman = isset($cocokReferer[1]) ? (parse_url($cocokReferer[1], PHP_URL_PATH) ?: '/') : '-';
            if ($waktu->isSameDay($hariIni)) {
                $jumlahHariIni++;
                $perHalaman[$halaman] = ($perHalaman[$halaman] ?? 0) + 1;
            }
            if ($waktu->greaterThanOrEqualTo($batasBaru)) {
                $jumlah15Menit++;
            }
            $rows[] = [
                'waktu' => $waktu->format('d/m H:i:s'),
                'client' => $cocokClient[1] ?? '-',
                'halaman' => $halaman,
            ];
        }
        arsort($perHalaman);

        return [
            'pesan' => $bacaan['pesan'],
            'jumlahHariIni' => $jumlahHariIni,
            'jumlah15Menit' => $jumlah15Menit,
            'perHalaman' => array_slice($perHalaman, 0, 10, true),
            'rows' => array_slice(array_reverse($rows), 0, 300),
        ];
    }

    /* Baris WARNING/ERROR dari log master PHP-FPM: max_children, executing too slow, timed out. */
    #[Computed]
    public function logFpm(): array
    {
        $bacaan = $this->bacaEkorBerkas(self::BERKAS_LOG_FPM, self::BATAS_BYTE_BACA);
        $hariIni = Carbon::today();
        $rows = [];
        $jumlahHariIni = ['maxChildren' => 0, 'dimatikan' => 0, 'lambat' => 0];
        $jumlah15Menit = ['maxChildren' => 0, 'dimatikan' => 0, 'lambat' => 0];
        $batasBaru = now()->subMinutes(15);

        foreach ($bacaan['baris'] as $baris) {
            if (!preg_match('/^\[(\d{2}-\w{3}-\d{4} [\d:]{8})\] (WARNING|ERROR|ALERT): (.*)$/', $baris, $cocok)) {
                continue;
            }
            $waktu = Carbon::createFromFormat('d-M-Y H:i:s', $cocok[1]);
            $jenis = match (true) {
                str_contains($cocok[3], 'max_children') => 'maxChildren',
                str_contains($cocok[3], 'timed out') => 'dimatikan',
                str_contains($cocok[3], 'too slow') => 'lambat',
                default => 'lain',
            };
            if ($waktu->isSameDay($hariIni) && isset($jumlahHariIni[$jenis])) {
                $jumlahHariIni[$jenis]++;
            }
            if ($waktu->greaterThanOrEqualTo($batasBaru) && isset($jumlah15Menit[$jenis])) {
                $jumlah15Menit[$jenis]++;
            }
            $rows[] = [
                'waktu' => $waktu->format('d/m H:i:s'),
                'level' => $cocok[2],
                'jenis' => $jenis,
                'pesan' => $cocok[3],
            ];
        }

        return [
            'pesan' => $bacaan['pesan'],
            'jumlahHariIni' => $jumlahHariIni,
            'jumlah15Menit' => $jumlah15Menit,
            'rows' => array_slice(array_reverse($rows), 0, 300),
        ];
    }

    /* Slowlog: satu entri = header waktu/pid + script_filename + frame stack (paling dalam di atas). */
    #[Computed]
    public function slowlog(): array
    {
        $bacaan = $this->bacaEkorBerkas(self::BERKAS_SLOWLOG, self::BATAS_BYTE_BACA);
        $hariIni = Carbon::today();
        $entriList = [];
        $entri = null;
        $jumlahHariIni = 0;

        foreach ($bacaan['baris'] as $baris) {
            if (preg_match('/^\[(\d{2}-\w{3}-\d{4} [\d:]{8})\]\s+\[pool (\S+)\] pid (\d+)/', $baris, $cocok)) {
                if ($entri !== null) {
                    $entriList[] = $entri;
                }
                $waktu = Carbon::createFromFormat('d-M-Y H:i:s', $cocok[1]);
                if ($waktu->isSameDay($hariIni)) {
                    $jumlahHariIni++;
                }
                $entri = ['waktu' => $waktu->format('d/m H:i:s'), 'pool' => $cocok[2], 'pid' => $cocok[3], 'frameList' => []];
                continue;
            }
            if ($entri === null || count($entri['frameList']) >= 30) {
                continue;
            }
            if (preg_match('/^\[0x[0-9a-f]+\] (.+?) (\/\S+:\d+)$/', $baris, $cocokFrame)) {
                $lokasi = $cocokFrame[2];
                $entri['frameList'][] = [
                    'fungsi' => $cocokFrame[1],
                    'lokasi' => $lokasi,
                    'sumber' => $this->sumberKelasKompilasi($lokasi),
                    'kodeAplikasi' => !str_contains($lokasi, '/vendor/') && !str_ends_with(strtok($lokasi, ':'), '/public/index.php'),
                ];
            }
        }
        if ($entri !== null) {
            $entriList[] = $entri;
        }

        return [
            'pesan' => $bacaan['pesan'],
            'jumlahHariIni' => $jumlahHariIni,
            'entriList' => array_slice(array_reverse($entriList), 0, 60),
        ];
    }

    /* Baris ERROR ke atas di storage/logs/laravel.log, dikelompokkan per pesan. */
    #[Computed]
    public function errorLaravel(): array
    {
        $bacaan = $this->bacaEkorBerkas(storage_path('logs/laravel.log'), self::BATAS_BYTE_LARAVEL);
        $hariIni = Carbon::today()->format('Y-m-d');
        $rows = [];
        $perPesan = [];
        $jumlahHariIni = 0;

        foreach ($bacaan['baris'] as $baris) {
            if (!preg_match('/^\[(\d{4}-\d{2}-\d{2}) ([\d:]{8})\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/', $baris, $cocok)) {
                continue;
            }
            $pesan = mb_strimwidth(preg_replace('/ \{".*$/', '', $cocok[4]), 0, 400, '…');
            if ($cocok[1] === $hariIni) {
                $jumlahHariIni++;
                $kunciPesan = mb_strimwidth($pesan, 0, 160, '…');
                $perPesan[$kunciPesan] = ($perPesan[$kunciPesan] ?? 0) + 1;
            }
            $rows[] = [
                'waktu' => Carbon::parse("{$cocok[1]} {$cocok[2]}")->format('d/m H:i:s'),
                'level' => $cocok[3],
                'pesan' => $pesan,
            ];
        }
        arsort($perPesan);

        return [
            'pesan' => $bacaan['pesan'],
            'jumlahHariIni' => $jumlahHariIni,
            'perPesan' => array_slice($perPesan, 0, 15, true),
            'rows' => array_slice(array_reverse($rows), 0, 200),
        ];
    }

    /**
     * Frame di storage/framework/views/livewire/classes/<hash>.php → berkas Volt aslinya,
     * diambil dari komentar path di baris awal berkas kompilasi (konvensi header repo ini).
     */
    private function sumberKelasKompilasi(string $lokasi): string
    {
        $berkas = strtok($lokasi, ':');
        if (!str_contains($berkas, '/storage/framework/views/') || !is_readable($berkas)) {
            return '';
        }
        $awalBerkas = (string) file_get_contents($berkas, false, null, 0, 600);
        return preg_match('/^\/\/\s*(resources\/\S+\.blade\.php)/m', $awalBerkas, $cocok) ? $cocok[1] : '';
    }

    /**
     * Ekor berkas log sebagai daftar baris. Berkas yang bisa dibaca langsung dibaca N byte terakhir;
     * selain itu lewat `sudo -n /usr/bin/tail -n 3000 <berkas>` — argumennya harus sama persis
     * dengan baris sudoers di tab Setup Server.
     */
    private function bacaEkorBerkas(string $berkas, int $batasByte): array
    {
        if (is_readable($berkas)) {
            $ukuran = filesize($berkas) ?: 0;
            $handle = fopen($berkas, 'rb');
            if ($handle === false) {
                return ['baris' => [], 'pesan' => 'Gagal membuka ' . $berkas];
            }
            if ($ukuran > $batasByte) {
                fseek($handle, -$batasByte, SEEK_END);
                fgets($handle);
            }
            $isi = stream_get_contents($handle) ?: '';
            fclose($handle);
            return ['baris' => preg_split('/\r?\n/', $isi), 'pesan' => ''];
        }

        if (!file_exists($berkas) && !is_dir(dirname($berkas))) {
            return ['baris' => [], 'pesan' => 'Berkas tidak ada: ' . $berkas];
        }

        try {
            $process = new Process(['sudo', '-n', '/usr/bin/tail', '-n', (string) self::JUMLAH_BARIS_SUDO, $berkas]);
            $process->setTimeout(5);
            $process->run();
            if (!$process->isSuccessful()) {
                return ['baris' => [], 'pesan' => 'Tidak bisa membaca ' . $berkas . ' — ' . (trim($process->getErrorOutput()) ?: 'sudo ditolak') . '. Lihat tab Setup Server.'];
            }
            return ['baris' => preg_split('/\r?\n/', $process->getOutput()), 'pesan' => ''];
        } catch (\Throwable $e) {
            return ['baris' => [], 'pesan' => 'Tidak bisa membaca ' . $berkas . ' — ' . $e->getMessage()];
        }
    }
};
?>

<div>

    <x-page-title title="Monitor Server"
        subtitle="Worker PHP-FPM, timeout Apache, slowlog & error Laravel — untuk melacak server yang tidak bisa diakses" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-800">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6 overflow-y-auto">

            @php
                $statusPool = $this->statusPool;
                $timeoutApache = $this->timeoutApache;
                $logFpm = $this->logFpm;
                $slowlog = $this->slowlog;
                $errorLaravel = $this->errorLaravel;
                $tabList = [
                    'ringkasan' => 'Ringkasan & Worker',
                    'apache' => 'Timeout Apache (' . $timeoutApache['jumlahHariIni'] . ')',
                    'fpm' => 'Log PHP-FPM',
                    'slowlog' => 'Slowlog (' . $slowlog['jumlahHariIni'] . ')',
                    'laravel' => 'Error Laravel (' . $errorLaravel['jumlahHariIni'] . ')',
                    'setup' => 'Setup Server',
                ];
            @endphp

            {{-- TOOLBAR: tab + refresh --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-0 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($tabList as $kunciTab => $labelTab)
                            <button type="button" wire:click="pilihTab('{{ $kunciTab }}')"
                                class="px-3 py-1.5 text-sm font-semibold rounded-full border {{ $tab === $kunciTab ? 'bg-blue-500 text-white border-blue-500' : 'bg-canvas text-muted border-hairline hover:bg-surface-card dark:bg-gray-900 dark:text-gray-300 dark:border-gray-700' }}">
                                {{ $labelTab }}
                            </button>
                        @endforeach
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <span class="text-sm text-muted dark:text-gray-400 whitespace-nowrap">
                            {{ now()->format('H:i:s') }} · hitungan "hari ini" sejak 00:00
                        </span>
                        <x-secondary-button type="button" wire:click="refresh" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="refresh">↻ Refresh</span>
                            <span wire:loading wire:target="refresh"><x-loading />...</span>
                        </x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- ============================================================ --}}
            {{-- RINGKASAN & WORKER                                             --}}
            {{-- ============================================================ --}}
            @if ($tab === 'ringkasan')
                <div wire:poll.10s="ambilSampel"></div>

                {{-- STATUS KESELURUHAN — ambang di kesehatan(), sama dengan tabel Tanda-tanda di bawah --}}
                @php
                    $kesehatan = $this->kesehatan;
                    $gayaStatus = [
                        'normal' => ['kotak' => 'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-700', 'teks' => 'text-emerald-800 dark:text-emerald-200', 'judul' => 'NORMAL', 'ikon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'ket' => 'Apache & PHP-FPM melayani tanpa antrean, timeout, atau worker penuh dalam 15 menit terakhir.'],
                        'waspada' => ['kotak' => 'bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-700', 'teks' => 'text-amber-800 dark:text-amber-200', 'judul' => 'WASPADA', 'ikon' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z', 'ket' => 'Server masih bisa diakses, tapi ada tanda beban berat atau masalah tadi. Pantau, cari penyebabnya di tab terkait.'],
                        'bahaya' => ['kotak' => 'bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-700', 'teks' => 'text-rose-800 dark:text-rose-200', 'judul' => 'BAHAYA', 'ikon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'ket' => 'Pengguna sedang/baru saja gagal membuka halaman. Lihat worker Sibuk terlama & tab Slowlog.'],
                    ][$kesehatan['level']];
                @endphp
                <div class="flex gap-3 p-4 mt-4 border rounded-2xl {{ $gayaStatus['kotak'] }}">
                    <svg class="w-8 h-8 shrink-0 {{ $gayaStatus['teks'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $gayaStatus['ikon'] }}" />
                    </svg>
                    <div class="{{ $gayaStatus['teks'] }}">
                        <p class="text-lg font-bold">Server {{ $gayaStatus['judul'] }}</p>
                        <p class="text-sm">{{ $gayaStatus['ket'] }}</p>
                        @if ($kesehatan['alasan'])
                            <ul class="mt-1 text-sm list-disc list-inside">
                                @foreach ($kesehatan['alasan'] as $alasan)
                                    <li>{{ $alasan }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4 lg:grid-cols-4">
                    @foreach ([
                        ['label' => 'Timeout Apache (504) hari ini', 'nilai' => $timeoutApache['jumlahHariIni'], 'bahaya' => $timeoutApache['jumlahHariIni'] > 0],
                        ['label' => 'Pool mentok max_children hari ini', 'nilai' => $logFpm['jumlahHariIni']['maxChildren'], 'bahaya' => $logFpm['jumlahHariIni']['maxChildren'] > 0],
                        ['label' => 'Worker dimatikan (timed out) hari ini', 'nilai' => $logFpm['jumlahHariIni']['dimatikan'], 'bahaya' => $logFpm['jumlahHariIni']['dimatikan'] > 0],
                        ['label' => 'Error Laravel hari ini', 'nilai' => $errorLaravel['jumlahHariIni'], 'bahaya' => $errorLaravel['jumlahHariIni'] > 0],
                    ] as $kartu)
                        <div class="p-4 border shadow-sm rounded-2xl bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                            <p class="text-sm text-muted dark:text-gray-400">{{ $kartu['label'] }}</p>
                            <p class="mt-1 text-2xl font-bold {{ $kartu['bahaya'] ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">
                                {{ $kartu['nilai'] }}
                            </p>
                        </div>
                    @endforeach
                </div>

                {{-- GRAFIK BERJALAN (opsional) — Chart.js dimuat dari CDN saat pertama dibutuhkan,
                     pola sama dengan Monitoring Dashboard Oracle (perfChart). --}}
                <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-hairline dark:border-gray-700">
                        <div>
                            <h3 class="text-base font-bold text-ink dark:text-gray-100">Grafik Worker Berjalan</h3>
                            <p class="text-sm text-muted dark:text-gray-400">Worker aktif & antrean tiap 10 detik, 10 menit terakhir — garis putus-putus = batas <code>pm.max_children</code>.</p>
                        </div>
                        <x-toggle wire:model.live="grafikBerjalan" :trueValue="true" :falseValue="false" label="Tampilkan grafik" />
                    </div>
                    @if ($grafikBerjalan)
                        @if ($statusPool === null)
                            <p class="px-4 py-6 text-sm text-muted dark:text-gray-400">Grafik butuh status pool — halaman ini tidak berjalan di PHP-FPM.</p>
                        @else
                            <div class="p-4" wire:ignore>
                                <div class="relative h-72">
                                    <canvas id="grafikWorkerFpm" aria-label="Grafik worker aktif dan antrean PHP-FPM"></canvas>
                                </div>
                            </div>
                            <p class="px-4 pb-3 text-sm text-muted dark:text-gray-400">
                                Grafik mulai mengisi sejak halaman ini dibuka (riwayat tidak disimpan). Garis <b>Worker aktif</b> yang menempel ke batas, atau <b>Antrean</b> di atas 0, berarti pengguna mulai menunggu.
                            </p>
                        @endif
                    @endif
                </div>

                @once
                    <script>
                        (function () {
                            let grafik = null;

                            function muatChartJs() {
                                return new Promise((resolve, reject) => {
                                    if (window.Chart) return resolve();
                                    let tag = document.querySelector('script[data-chartjs]');
                                    if (tag) {
                                        tag.addEventListener('load', resolve);
                                        tag.addEventListener('error', reject);
                                        return;
                                    }
                                    tag = document.createElement('script');
                                    tag.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                                    tag.defer = tag.async = true;
                                    tag.dataset.chartjs = '';
                                    tag.onload = resolve;
                                    tag.onerror = reject;
                                    document.head.appendChild(tag);
                                });
                            }

                            // Warna seri tervalidasi (skill dataviz: biru = slot 1, oranye = slot 2; langkah gelap untuk dark mode).
                            function warna() {
                                const gelap = document.documentElement.classList.contains('dark');
                                return {
                                    aktif: gelap ? '#3987e5' : '#2a78d6',
                                    antrean: gelap ? '#d95926' : '#eb6834',
                                    batas: gelap ? '#9ca3af' : '#6b7280',
                                    grid: gelap ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)',
                                    teks: gelap ? '#c3c2b7' : '#52514e',
                                };
                            }

                            function siapkanGrafik() {
                                const el = document.getElementById('grafikWorkerFpm');
                                if (!el) return null;
                                // Kanvas lama hilang (ganti tab / grafik dimatikan) → buat ulang di kanvas baru.
                                if (grafik && grafik.canvas !== el) {
                                    grafik.destroy();
                                    grafik = null;
                                }
                                if (grafik) return grafik;
                                const w = warna();
                                grafik = new Chart(el.getContext('2d'), {
                                    type: 'line',
                                    data: {
                                        labels: [],
                                        datasets: [
                                            { label: 'Worker aktif', data: [], borderColor: w.aktif, backgroundColor: w.aktif, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.2 },
                                            { label: 'Antrean', data: [], borderColor: w.antrean, backgroundColor: w.antrean, borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, tension: 0.2 },
                                            { label: 'Batas max_children', data: [], borderColor: w.batas, borderWidth: 1, borderDash: [6, 4], pointRadius: 0, pointHoverRadius: 0 },
                                        ],
                                    },
                                    options: {
                                        animation: false,
                                        maintainAspectRatio: false,
                                        interaction: { mode: 'index', intersect: false },
                                        plugins: {
                                            legend: { labels: { color: w.teks, boxWidth: 12, boxHeight: 2 } },
                                            tooltip: { mode: 'index', intersect: false },
                                        },
                                        scales: {
                                            x: { ticks: { color: w.teks, maxTicksLimit: 8 }, grid: { display: false } },
                                            y: { beginAtZero: true, ticks: { color: w.teks, precision: 0 }, grid: { color: w.grid }, title: { display: true, text: 'Jumlah', color: w.teks } },
                                        },
                                    },
                                });
                                return grafik;
                            }

                            window.addEventListener('monitor-server-sampel', async (ev) => {
                                try {
                                    await muatChartJs();
                                    // Tunggu morph Livewire selesai memasang kanvas.
                                    requestAnimationFrame(() => {
                                        const chart = siapkanGrafik();
                                        if (!chart) return;
                                        const { jam, aktif, antrean, batas } = ev.detail;
                                        chart.data.labels = jam;
                                        chart.data.datasets[0].data = aktif;
                                        chart.data.datasets[1].data = antrean;
                                        chart.data.datasets[2].data = batas ? jam.map(() => batas) : [];
                                        chart.data.datasets[2].hidden = !batas;
                                        chart.update('none');
                                    });
                                } catch {}
                            });
                        })();
                    </script>
                @endonce

                <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                        <h3 class="text-base font-bold text-ink dark:text-gray-100">Worker PHP-FPM (live, refresh 10 detik)</h3>
                    </div>
                    @if ($statusPool === null)
                        <p class="px-4 py-6 text-sm text-muted dark:text-gray-400">
                            Status pool tidak tersedia — halaman ini tidak berjalan di PHP-FPM (mis. <code>php artisan serve</code>).
                        </p>
                    @else
                        <div class="grid grid-cols-2 gap-3 p-4 text-sm lg:grid-cols-4">
                            <div><span class="text-muted dark:text-gray-400">Pool:</span> <b class="font-mono">{{ $statusPool['pool'] }}</b></div>
                            <div><span class="text-muted dark:text-gray-400">Aktif / Idle / Total:</span>
                                <b>{{ $statusPool['aktif'] }}</b> / {{ $statusPool['idle'] }} / {{ $statusPool['total'] }}
                                <span class="text-muted dark:text-gray-400">· batas {{ $this->maxChildren ?? '?' }}</span></div>
                            <div><span class="text-muted dark:text-gray-400">Aktif tertinggi:</span> <b>{{ $statusPool['aktifTertinggi'] }}</b></div>
                            <div><span class="text-muted dark:text-gray-400">Mentok max_children:</span>
                                <b class="{{ $statusPool['mentokMaxChildren'] > 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">{{ $statusPool['mentokMaxChildren'] }}×</b></div>
                            <div><span class="text-muted dark:text-gray-400">Antrean sekarang / tertinggi:</span>
                                <b class="{{ $statusPool['antrean'] > 0 ? 'text-rose-700 dark:text-rose-300' : '' }}">{{ $statusPool['antrean'] }}</b> / {{ $statusPool['antreanTertinggi'] }}</div>
                            <div><span class="text-muted dark:text-gray-400">Request lambat (&gt;slowlog):</span> <b>{{ $statusPool['requestLambat'] }}</b></div>
                            <div><span class="text-muted dark:text-gray-400">Request dilayani:</span> {{ number_format($statusPool['requestDiterima'], 0, ',', '.') }}</div>
                            <div><span class="text-muted dark:text-gray-400">Pool jalan sejak:</span> {{ $statusPool['mulai'] }}</div>
                        </div>
                        <div class="overflow-x-auto border-t border-hairline dark:border-gray-700">
                            <table class="min-w-full text-sm">
                                <thead class="text-muted bg-surface-card dark:bg-gray-800 dark:text-gray-200">
                                    <tr class="text-left">
                                        <th class="px-4 py-2 font-semibold">PID</th>
                                        <th class="px-4 py-2 font-semibold">STATUS</th>
                                        <th class="px-4 py-2 font-semibold text-right">DURASI</th>
                                        <th class="px-4 py-2 font-semibold">REQUEST</th>
                                        <th class="px-4 py-2 font-semibold text-right">MEMORI</th>
                                        <th class="px-4 py-2 font-semibold text-right">JML REQUEST</th>
                                    </tr>
                                </thead>
                                <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                    @foreach ($statusPool['workerList'] as $row)
                                        <tr wire:key="worker-{{ $row['pid'] }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                            <td class="px-4 py-2 font-mono">{{ $row['pid'] }}</td>
                                            <td class="px-4 py-2">
                                                @if ($row['state'] === 'Running')
                                                    <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Sibuk</span>
                                                @else
                                                    <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400">{{ $row['state'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 font-mono text-right whitespace-nowrap {{ $row['state'] === 'Running' && $row['durasiDetik'] >= 15 ? 'text-rose-700 dark:text-rose-300 font-bold' : '' }}">
                                                {{ $row['durasiDetik'] }}s
                                            </td>
                                            <td class="px-4 py-2 font-mono break-all">{{ $row['method'] }} {{ $row['uri'] }}</td>
                                            <td class="px-4 py-2 font-mono text-right">{{ $row['memoriMb'] }} MB</td>
                                            <td class="px-4 py-2 font-mono text-right">{{ $row['jumlahRequest'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="px-4 py-3 text-sm border-t text-muted border-hairline dark:border-gray-700 dark:text-gray-400">
                            Worker <b>Sibuk</b> dengan durasi lama = request yang sedang menahan worker. Bila Aktif mendekati
                            <code>pm.max_children</code> dan Antrean &gt; 0, server mulai menjawab 504. Untuk Idle, DURASI adalah lama request terakhirnya.
                        </p>
                    @endif
                </div>

                {{-- TANDA-TANDA — sama dengan ambang di kesehatan(); ubah keduanya bersamaan --}}
                <div class="mt-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                        <h3 class="text-base font-bold text-ink dark:text-gray-100">Tanda-tanda: kapan harus waspada</h3>
                        <p class="text-sm text-muted dark:text-gray-400">Status di atas dihitung dari tabel ini. "Baru" = 15 menit terakhir.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-muted bg-surface-card dark:bg-gray-800 dark:text-gray-200">
                                <tr class="text-left">
                                    <th class="px-4 py-2 font-semibold">INDIKATOR</th>
                                    <th class="px-4 py-2 font-semibold text-emerald-700 dark:text-emerald-300">NORMAL</th>
                                    <th class="px-4 py-2 font-semibold text-amber-700 dark:text-amber-300">WASPADA</th>
                                    <th class="px-4 py-2 font-semibold text-rose-700 dark:text-rose-300">BAHAYA</th>
                                    <th class="px-4 py-2 font-semibold">YANG DILAKUKAN</th>
                                </tr>
                            </thead>
                            <tbody class="align-top text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                @foreach ([
                                    ['Worker aktif dibanding batas', '&lt; 70%', '70–89%', '≥ 90%', 'Lihat worker Sibuk terlama di tabel Worker; bila sering tinggi padahal request cepat, naikkan pm.max_children (cek RAM & sesi Oracle).'],
                                    ['Antrean request', '0', '—', '&gt; 0', 'Semua worker sibuk, pengguna menunggu. Cari request yang menahan worker (durasi terlama) & tab Slowlog.'],
                                    ['Worker mengerjakan 1 request', '&lt; 15 detik', '≥ 15 detik', '—', 'Biasanya laporan/query berat. Catat halamannya (kolom REQUEST) untuk dioptimalkan.'],
                                    ['Timeout Apache (504)', '0 hari ini', 'ada hari ini', 'ada di 15 menit terakhir', 'Tab Timeout Apache → kolom halaman menunjukkan asal pengguna yang gagal.'],
                                    ['Pool mentok max_children', '0 hari ini', 'ada hari ini', 'ada di 15 menit terakhir', 'Tab Log PHP-FPM. Bila berulang tiap jam sibuk, kapasitas pool kurang atau ada request yang macet.'],
                                    ['Worker dimatikan (timed out)', '0 hari ini', 'ada hari ini', 'ada di 15 menit terakhir', 'Request melebihi request_terminate_timeout (300 dtk). Tab Slowlog menunjukkan baris kode penyebabnya.'],
                                ] as [$indikator, $normal, $waspada, $bahaya, $tindakan])
                                    <tr>
                                        <td class="px-4 py-2 font-semibold">{{ $indikator }}</td>
                                        <td class="px-4 py-2">{!! $normal !!}</td>
                                        <td class="px-4 py-2">{!! $waspada !!}</td>
                                        <td class="px-4 py-2">{!! $bahaya !!}</td>
                                        <td class="px-4 py-2 text-muted dark:text-gray-400">{{ $tindakan }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="px-4 py-3 text-sm border-t text-muted border-hairline dark:border-gray-700 dark:text-gray-400">
                        <b>Error Laravel</b> tidak ikut menentukan status: error aplikasi (validasi, data kosong) tidak berarti server tak bisa diakses.
                        Perhatikan bila satu pesan yang sama muncul ratusan kali. Status ini hanya untuk pool <code>{{ $statusPool['pool'] ?? 'ini' }}</code>;
                        pool lain (mis. <code>www</code> untuk SIAP/SIGAP) hanya terlihat lewat tab Log PHP-FPM.
                    </p>
                </div>

                {{-- Panduan --}}
                <details class="mt-4 overflow-hidden bg-blue-50 border border-blue-200 rounded-2xl dark:bg-blue-900/20 dark:border-blue-700 group">
                    <summary class="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-blue-900 cursor-pointer select-none dark:text-blue-200">
                        <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                        Cara Membaca
                    </summary>
                    <div class="px-4 pt-1 pb-3 space-y-2 text-sm leading-relaxed text-muted dark:text-gray-300">
                        <ol class="space-y-1 list-decimal list-inside">
                            <li><b>Timeout Apache</b> — tiap baris = satu pengguna kena 504 karena tidak ada worker kosong. Kolom halaman menunjukkan dari mana pengguna itu berasal.</li>
                            <li><b>Log PHP-FPM</b> — <i>max_children</i> = pool penuh; <i>timed out</i> = worker dimatikan setelah <code>request_terminate_timeout</code>.</li>
                            <li><b>Slowlog</b> — stack trace request yang lebih dari <code>request_slowlog_timeout</code>. Baris tebal = kode aplikasi (bukan vendor): di situlah yang perlu diperbaiki.</li>
                            <li><b>Error Laravel</b> — dikelompokkan per pesan agar error yang berulang mudah terlihat.</li>
                        </ol>
                    </div>
                </details>
            @endif

            {{-- ============================================================ --}}
            {{-- TIMEOUT APACHE                                                 --}}
            {{-- ============================================================ --}}
            @if ($tab === 'apache')
                @if ($timeoutApache['pesan'] !== '')
                    <p class="p-3 mt-4 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700/50 dark:text-amber-300">{{ $timeoutApache['pesan'] }}</p>
                @endif

                @if (!empty($timeoutApache['perHalaman']))
                    <div class="p-4 mt-4 border shadow-sm rounded-2xl bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                        <h3 class="mb-2 text-base font-bold text-ink dark:text-gray-100">Halaman asal timeout hari ini</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($timeoutApache['perHalaman'] as $halaman => $jumlah)
                                <span class="px-2.5 py-1 text-sm font-mono rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">{{ $halaman }} · {{ $jumlah }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-4 overflow-x-auto bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full text-sm">
                        <thead class="text-muted bg-surface-card dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-2 font-semibold">WAKTU</th>
                                <th class="px-4 py-2 font-semibold">CLIENT</th>
                                <th class="px-4 py-2 font-semibold">HALAMAN ASAL</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($timeoutApache['rows'] as $row)
                                <tr wire:key="apache-{{ $loop->index }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-2 font-mono whitespace-nowrap">{{ $row['waktu'] }}</td>
                                    <td class="px-4 py-2 font-mono">{{ $row['client'] }}</td>
                                    <td class="px-4 py-2 font-mono">{{ $row['halaman'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-10 text-center text-muted dark:text-gray-400">Tidak ada timeout AH01075 di log.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- LOG PHP-FPM                                                    --}}
            {{-- ============================================================ --}}
            @if ($tab === 'fpm')
                @if ($logFpm['pesan'] !== '')
                    <p class="p-3 mt-4 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700/50 dark:text-amber-300">{{ $logFpm['pesan'] }}</p>
                @endif

                <div class="mt-4 overflow-x-auto bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full text-sm">
                        <thead class="text-muted bg-surface-card dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-2 font-semibold">WAKTU</th>
                                <th class="px-4 py-2 font-semibold">JENIS</th>
                                <th class="px-4 py-2 font-semibold">PESAN</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($logFpm['rows'] as $row)
                                <tr wire:key="fpm-{{ $loop->index }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-2 font-mono whitespace-nowrap">{{ $row['waktu'] }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @if ($row['jenis'] === 'maxChildren')
                                            <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Pool penuh</span>
                                        @elseif ($row['jenis'] === 'dimatikan')
                                            <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Dimatikan</span>
                                        @elseif ($row['jenis'] === 'lambat')
                                            <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Lambat</span>
                                        @else
                                            <span class="px-2 py-0.5 text-sm font-semibold rounded-full bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400">{{ $row['level'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 font-mono break-all">{{ $row['pesan'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-10 text-center text-muted dark:text-gray-400">Tidak ada WARNING/ERROR di log PHP-FPM.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- SLOWLOG                                                        --}}
            {{-- ============================================================ --}}
            @if ($tab === 'slowlog')
                @if ($slowlog['pesan'] !== '')
                    <p class="p-3 mt-4 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700/50 dark:text-amber-300">{{ $slowlog['pesan'] }}</p>
                @endif

                <div class="mt-4 space-y-3">
                    @forelse ($slowlog['entriList'] as $entri)
                        <details wire:key="slow-{{ $loop->index }}" class="overflow-hidden bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900 group">
                            <summary class="flex flex-wrap items-center gap-3 px-4 py-2 text-sm cursor-pointer select-none">
                                <span class="font-mono font-semibold">{{ $entri['waktu'] }}</span>
                                <span class="font-mono text-muted dark:text-gray-400">pool {{ $entri['pool'] }} · pid {{ $entri['pid'] }}</span>
                                @php $frameAplikasi = collect($entri['frameList'])->firstWhere('kodeAplikasi', true); @endphp
                                @if ($frameAplikasi)
                                    <span class="font-mono break-all text-rose-700 dark:text-rose-300">{{ $frameAplikasi['sumber'] ?: $frameAplikasi['lokasi'] }}</span>
                                @endif
                            </summary>
                            <div class="px-4 pt-1 pb-3 overflow-x-auto">
                                @foreach ($entri['frameList'] as $frame)
                                    <div class="font-mono text-xs break-all {{ $frame['kodeAplikasi'] ? 'font-bold text-ink dark:text-gray-100' : 'text-muted dark:text-gray-400' }}">
                                        {{ $frame['fungsi'] }} <span class="text-blue-700 dark:text-blue-300">{{ $frame['lokasi'] }}</span>
                                        @if ($frame['sumber'] !== '')
                                            <span class="text-rose-700 dark:text-rose-300">← {{ $frame['sumber'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <p class="px-4 py-10 text-center text-muted dark:text-gray-400">Belum ada entri slowlog.</p>
                    @endforelse
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- ERROR LARAVEL                                                  --}}
            {{-- ============================================================ --}}
            @if ($tab === 'laravel')
                @if ($errorLaravel['pesan'] !== '')
                    <p class="p-3 mt-4 text-sm border rounded-lg bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-700/50 dark:text-amber-300">{{ $errorLaravel['pesan'] }}</p>
                @endif

                @if (!empty($errorLaravel['perPesan']))
                    <div class="mt-4 overflow-x-auto bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="px-4 py-3 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-base font-bold text-ink dark:text-gray-100">Error terbanyak hari ini</h3>
                        </div>
                        <table class="min-w-full text-sm">
                            <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                                @foreach ($errorLaravel['perPesan'] as $pesan => $jumlah)
                                    <tr wire:key="laravel-kelompok-{{ $loop->index }}">
                                        <td class="px-4 py-2 font-mono font-bold text-right whitespace-nowrap text-rose-700 dark:text-rose-300">{{ $jumlah }}×</td>
                                        <td class="px-4 py-2 font-mono break-all">{{ $pesan }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="mt-4 overflow-x-auto bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                    <table class="min-w-full text-sm">
                        <thead class="text-muted bg-surface-card dark:bg-gray-800 dark:text-gray-200">
                            <tr class="text-left">
                                <th class="px-4 py-2 font-semibold">WAKTU</th>
                                <th class="px-4 py-2 font-semibold">LEVEL</th>
                                <th class="px-4 py-2 font-semibold">PESAN</th>
                            </tr>
                        </thead>
                        <tbody class="text-body divide-y divide-hairline dark:divide-gray-700 dark:text-gray-200">
                            @forelse ($errorLaravel['rows'] as $row)
                                <tr wire:key="laravel-{{ $loop->index }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-2 font-mono whitespace-nowrap">{{ $row['waktu'] }}</td>
                                    <td class="px-4 py-2 font-mono">{{ $row['level'] }}</td>
                                    <td class="px-4 py-2 font-mono break-all">{{ $row['pesan'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-10 text-center text-muted dark:text-gray-400">Tidak ada ERROR di bagian akhir laravel.log.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- SETUP SERVER                                                   --}}
            {{-- ============================================================ --}}
            @if ($tab === 'setup')
                <div class="mt-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b border-blue-200 dark:border-blue-700 bg-blue-100/50 dark:bg-blue-900/30">
                        <h3 class="text-base font-bold text-blue-900 dark:text-blue-200">Setup Server (sekali per server)</h3>
                        <p class="text-sm text-blue-700 dark:text-blue-300">
                            Log Apache & PHP-FPM milik root. User PHP halaman ini:
                            <code class="px-1 rounded bg-blue-100 dark:bg-blue-900/40 font-mono">{{ $userProsesPhp }}</code>
                        </p>
                    </div>
                    <div class="p-4 space-y-3 bg-canvas dark:bg-gray-900">
                        @foreach ([
                            [
                                'judul' => 'Buka file sudoers baru',
                                'ket' => 'Selalu lewat visudo — salah ketik di sudoers bisa merusak sudo.',
                                'kode' => 'sudo visudo -f /etc/sudoers.d/sirus-monitor',
                            ],
                            [
                                'judul' => 'Isi dengan satu baris ini (izin hanya untuk perintah & berkas persis ini), simpan, lalu kunci izinnya',
                                'ket' => 'Baris pertama masuk ke visudo; baris kedua dijalankan di terminal.',
                                'kode' => $userProsesPhp . ' ALL=(root) NOPASSWD: /usr/bin/tail -n 3000 /var/log/php8.4-fpm.log, /usr/bin/tail -n 3000 /var/log/apache2/sirus-ssl-error.log' . "\n" . 'sudo chmod 440 /etc/sudoers.d/sirus-monitor',
                            ],
                            [
                                'judul' => 'Uji dari terminal',
                                'ket' => 'Muncul isi log = benar. "password is required" = nama user atau argumen tidak sama persis.',
                                'kode' => 'sudo -u ' . $userProsesPhp . ' sudo -n /usr/bin/tail -n 3000 /var/log/php8.4-fpm.log | tail -3',
                            ],
                            [
                                'judul' => 'Slowlog harus aktif di pool',
                                'ket' => 'Di /etc/php/8.4/fpm/pool.d/sirus.conf, lalu sudo systemctl reload php8.4-fpm. Berkasnya milik user pool, jadi dibaca langsung tanpa sudo.',
                                'kode' => "request_slowlog_timeout = 15s\nslowlog = /var/log/php8.4-fpm-sirus-slow.log\nrequest_terminate_timeout = 300s\npm.max_requests = 500",
                            ],
                        ] as $nomor => $langkah)
                            <div class="p-3 rounded-lg bg-surface-soft dark:bg-gray-800/60 border border-hairline dark:border-gray-700">
                                <div class="flex items-start gap-2 mb-1">
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-500 text-white text-[10px] font-bold shrink-0">{{ $nomor + 1 }}</span>
                                    <p class="text-xs font-bold text-ink dark:text-gray-100">{{ $langkah['judul'] }}</p>
                                </div>
                                <p class="text-[11px] text-muted dark:text-gray-400 leading-relaxed">{{ $langkah['ket'] }}</p>
                                <code class="block mt-1.5 px-2 py-1.5 text-[10px] font-mono whitespace-pre-wrap break-all rounded bg-gray-900 text-gray-100 dark:bg-gray-800">{{ $langkah['kode'] }}</code>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
