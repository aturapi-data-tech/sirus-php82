<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\BPJS\AntrianTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Support\TaskIdAntrean;

/**
 * KOMPONEN AKSI Task ID tahap POLI RJ (TaskId4 Masuk Poli, TaskId5 Panggil
 * Antrian, modal Banding TaskId Antrean SIRUS ⇄ BPJS) — berisi SEMUA fungsi/logika.
 *
 * Arsitektur "cetak-pattern": komponen ini di-mount SEKALI sebagai sibling di
 * pelayanan-rj (bukan per baris). Tombol tiap baris ada di pelayanan-rj dan
 * memicu komponen ini via wire:click="$dispatch('task-id-poli-proses', {...})"
 * (aksi Livewire, bukan Alpine).
 *
 * Sebelumnya komponen ini di-mount per baris dengan prop #[Reactive]. Saat list
 * re-render pasca 'refresh-after-rj.saved', SEMUA child reactive (s/d 100 baris)
 * ikut di-sync satu batch → TooManyComponents (100 baris + list = 101). Dengan
 * mount sekali, batch pasca-simpan tak lagi skala jumlah baris.
 */
new class extends Component {
    use EmrRJTrait, AntrianTrait;

    public ?int $rjNo = null;

    /** Modal banding TaskId: identitas kunjungan (kecil, aman sebagai properti publik). */
    public array $identitasTask = [];

    /** Baris banding per task (maks 8: task 1–7 & 99). */
    public array $barisTask = [];

    public string $pesanGagalTask = '';

    public bool $poliSpesialis = false;

    /* ===============================
     | ROUTER — dipicu tombol baris via wire:click $dispatch
     |
     | Detail event: { rjNo, aksi } dengan aksi ∈ {'4','5','antrean'}.
     =============================== */
    #[On('task-id-poli-proses')]
    public function proses(int $rjNo, string $aksi): void
    {
        $this->rjNo = $rjNo;

        match ($aksi) {
            '4'       => $this->prosesTaskId4(),
            '5'       => $this->prosesTaskId5(),
            'antrean' => $this->prosesTaskidAntrean(),
            default   => null,
        };
    }

    /* ===============================
     | PROSES TASK ID 4 (Masuk Poli)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId3 prerequisite
     | 2. Set taskId4 timestamp jika belum ada
     | 3. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
     | 4. lockRJRow + update waktu_masuk_poli + patch taskIdPelayanan — atomik
    =============================== */
    public function prosesTaskId4(): void
    {
        // 1. Guard: rjNo belum di-set
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        try {
            // 2. Ambil data RJ — tanpa lock dulu, hanya untuk baca awal
            $data = $this->findDataRJ($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // 3. Validasi prerequisite: taskId3 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId3'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId3 (Masuk Antrian) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            // 4. Validasi noBooking
            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // 5. Inisialisasi taskIdPelayanan jika belum ada
            $data['taskIdPelayanan'] ??= [];

            $waktuSekarang = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');

            // Penanda untuk log aktivitas: dicatat HANYA bila klik ini benar-benar
            // mengubah sesuatu — stempel baru atau kiriman BPJS. Klik pada baris yang
            // sudah lengkap tidak perlu meninggalkan jejak, itu cuma bising.
            $sudahTercatat = !empty($data['taskIdPelayanan']['taskId4']);
            $kodeBpjs = null;

            // 6. Notifikasi jika taskId4 sudah pernah tercatat
            if ($sudahTercatat) {
                $this->dispatch('toast', type: 'warning', message: "TaskId4 sudah tercatat: {$data['taskIdPelayanan']['taskId4']}", title: 'Info');
            }

            // 7. Set taskId4 jika belum ada
            if (empty($data['taskIdPelayanan']['taskId4'])) {
                $data['taskIdPelayanan']['taskId4'] = $waktuSekarang;
            }

            // 8. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskId4Status'] ?? '';

                if (empty($status) || ($status != 200 && $status != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId4'], config('app.timezone'))->timestamp * 1000;

                    $response = AntrianTrait::update_antrean($noBooking, 4, $waktuTimestamp, '')->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';
                    $isSuccess = $code == 200 || $code == 208;

                    $data['taskIdPelayanan']['taskId4Status'] = $code;
                    $kodeBpjs = $code;

                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 4: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                } else {
                    $this->dispatch('toast', type: 'info', message: 'TaskId 4 sudah pernah dikirim ke BPJS', title: 'Info');
                }
            }

            // 9. Simpan ke DB — lock + update waktu_masuk_poli + patch taskIdPelayanan atomik
            DB::transaction(function () use ($data, $waktuSekarang, $sudahTercatat, $kodeBpjs) {
                $this->lockRJRow($this->rjNo);

                // Update waktu_masuk_poli di header
                DB::table('rstxn_rjhdrs')
                    ->where('rj_no', $this->rjNo)
                    ->update([
                        'waktu_masuk_poli' => DB::raw("to_date('" . $waktuSekarang . "','dd/mm/yyyy hh24:mi:ss')"),
                    ]);

                // Re-fetch setelah lock — patch hanya key taskIdPelayanan
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                $this->updateJsonRJ($this->rjNo, $existingData);

                // Log aktivitas WAJIB di dalam transaksi setelah lockRJRow —
                // appendAdminLogRJ membaca lalu menulis ulang JSON yang sama.
                if (!$sudahTercatat || $kodeBpjs !== null) {
                    $this->appendAdminLogRJ(
                        $this->rjNo,
                        TaskIdAntrean::keterangan(4, $existingData['taskIdPelayanan']['taskId4'] ?? '-', $kodeBpjs),
                    );
                }
            });

            $this->dispatch('toast', type: 'success', message: "Berhasil masuk poli pada {$waktuSekarang}", title: 'Berhasil');
            $this->dispatch('refresh-after-rj.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), title: 'Error');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        }
    }

    /* ===============================
     | PROSES TASK ID 5 (Panggil Antrian)
     |
     | Alur:
     | 1. Guard rjNo + data kosong + noBooking + taskId4 prerequisite
     | 2. Set taskId5 timestamp jika belum ada
     | 3. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
     | 4. lockRJRow + patch hanya key taskIdPelayanan — atomik
    =============================== */
    public function prosesTaskId5(): void
    {
        // 1. Guard: rjNo belum di-set
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        try {
            // 2. Ambil data RJ — tanpa lock dulu, hanya untuk baca awal
            $data = $this->findDataRJ($this->rjNo);

            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            // 3. Validasi prerequisite: taskId4 harus sudah ada
            if (empty($data['taskIdPelayanan']['taskId4'] ?? null)) {
                $this->dispatch('toast', type: 'error', message: 'TaskId4 (Masuk Poli) harus dilakukan terlebih dahulu', title: 'Gagal');
                return;
            }

            // 4. Validasi noBooking
            $noBooking = $data['noBooking'] ?? null;
            if (empty($noBooking)) {
                $this->dispatch('toast', type: 'error', message: 'No Booking tidak ditemukan', title: 'Error');
                return;
            }

            // 5. Inisialisasi taskIdPelayanan jika belum ada
            $data['taskIdPelayanan'] ??= [];

            $sudahTercatat = !empty($data['taskIdPelayanan']['taskId5']);
            $kodeBpjs = null;

            // 6. Notifikasi jika taskId5 sudah pernah tercatat
            if ($sudahTercatat) {
                $this->dispatch('toast', type: 'warning', message: "TaskId5 sudah tercatat: {$data['taskIdPelayanan']['taskId5']}", title: 'Info');
            }

            // 7. Set taskId5 jika belum ada
            if (empty($data['taskIdPelayanan']['taskId5'])) {
                $data['taskIdPelayanan']['taskId5'] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s');
            }

            // 8. Push ke BPJS jika poli spesialis — DI LUAR transaksi (API call)
            if ($this->isPoliSpesialis($data['poliId'] ?? '')) {
                $status = $data['taskIdPelayanan']['taskId5Status'] ?? '';

                if (empty($status) || ($status != 200 && $status != 208)) {
                    $waktuTimestamp = Carbon::createFromFormat('d/m/Y H:i:s', $data['taskIdPelayanan']['taskId5'], config('app.timezone'))->timestamp * 1000;

                    $response = AntrianTrait::update_antrean($noBooking, 5, $waktuTimestamp, '')->getOriginalContent();
                    $code = $response['metadata']['code'] ?? '';
                    $message = $response['metadata']['message'] ?? '';
                    $isSuccess = $code == 200 || $code == 208;

                    $data['taskIdPelayanan']['taskId5Status'] = $code;
                    $kodeBpjs = $code;

                    $this->dispatch('toast', type: $isSuccess ? 'success' : 'error', message: "TaskId 5: {$message}", title: $isSuccess ? 'Berhasil' : 'Gagal');
                } else {
                    $this->dispatch('toast', type: 'info', message: 'TaskId 5 sudah pernah dikirim ke BPJS', title: 'Info');
                }
            }

            // 9. Simpan ke DB — lock + patch hanya key taskIdPelayanan
            DB::transaction(function () use ($data, $sudahTercatat, $kodeBpjs) {
                $this->lockRJRow($this->rjNo);

                // Re-fetch setelah lock — patch hanya key taskIdPelayanan
                $existingData = $this->findDataRJ($this->rjNo) ?? [];

                if (empty($existingData)) {
                    throw new \RuntimeException('Data RJ tidak ditemukan saat akan disimpan.');
                }

                $existingData['taskIdPelayanan'] = $data['taskIdPelayanan'];
                $this->updateJsonRJ($this->rjNo, $existingData);

                if (!$sudahTercatat || $kodeBpjs !== null) {
                    $this->appendAdminLogRJ(
                        $this->rjNo,
                        TaskIdAntrean::keterangan(5, $existingData['taskIdPelayanan']['taskId5'] ?? '-', $kodeBpjs),
                    );
                }
            });

            $this->dispatch('refresh-after-rj.saved');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage(), title: 'Error');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Terjadi kesalahan: ' . $e->getMessage(), title: 'Error');
        }
    }

    /* ===============================
     | BANDING TASK ID ANTREAN — SIRUS ⇄ BPJS (modal, hanya baca)
     |
     | getlisttask BPJS mengembalikan DAFTAR langsung di `response`:
     |   [{taskid, taskname, wakturs: "dd-mm-YYYY HH:ii:ss WIB", waktu: "... WIB", kodebooking}]
     |   wakturs = waktu yang DIKIRIM RS, waktu = kapan BPJS menerimanya.
     | (Kode lama membaca response.listTaskId → selalu kosong.)
     | Tidak menulis apa pun ke DB / JSON — aman diklik berulang.
    =============================== */
    public function prosesTaskidAntrean(): void
    {
        if (empty($this->rjNo)) {
            $this->dispatch('toast', type: 'warning', message: 'Nomor RJ tidak boleh kosong', title: 'Peringatan');
            return;
        }

        $this->muatBandingTask();
        if (!empty($this->identitasTask)) {
            $this->dispatch('open-modal', name: 'banding-task-id-antrean');
        }
    }

    public function muatUlangTask(): void
    {
        $this->muatBandingTask();
    }

    private function muatBandingTask(): void
    {
        $this->identitasTask = [];
        $this->barisTask = [];
        $this->pesanGagalTask = '';

        try {
            $data = $this->findDataRJ($this->rjNo);
            if (empty($data)) {
                $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan', title: 'Error');
                return;
            }

            $noBooking = trim((string) ($data['noBooking'] ?? ''));
            $this->poliSpesialis = $this->isPoliSpesialis($data['poliId'] ?? '');
            $this->identitasTask = [
                'regNo' => $data['regNo'] ?? '',
                'regName' => $data['regName'] ?? '',
                'poliDesc' => $data['poliDesc'] ?? '',
                'drDesc' => $data['drDesc'] ?? '',
                'rjDate' => $data['rjDate'] ?? '',
                'noBooking' => $noBooking,
            ];

            // Sisi SIRUS — taskIdPelayanan di akar JSON RJ (d/m/Y H:i:s) + kode kirimnya.
            $taskSirus = $data['taskIdPelayanan'] ?? [];

            // Sisi BPJS
            $taskBpjs = [];
            if ($noBooking === '') {
                $this->pesanGagalTask = 'No Booking kosong — kunjungan ini tidak terdaftar di Antrean BPJS.';
            } else {
                $response = AntrianTrait::taskid_antrean($noBooking)->getOriginalContent();
                $code = $response['metadata']['code'] ?? '';
                if ($code == 200) {
                    foreach ((array) ($response['response'] ?? []) as $item) {
                        if (isset($item['taskid'])) {
                            $taskBpjs[(string) $item['taskid']] = $item;
                        }
                    }
                } else {
                    $this->pesanGagalTask = 'BPJS: ' . $code . ' ' . ($response['metadata']['message'] ?? 'gagal mengambil daftar task');
                }
            }

            $nomorTask = collect(['1', '2', '3', '4', '5', '6', '7', '99'])
                ->merge(array_keys($taskBpjs))->unique()
                ->sortBy(fn ($n) => (int) $n)->values();

            foreach ($nomorTask as $n) {
                $waktuSirus = trim((string) ($taskSirus['taskId' . $n] ?? ''));
                $bpjs = $taskBpjs[$n] ?? null;
                $waktuRsBpjs = $this->parseWaktuBpjs($bpjs['wakturs'] ?? null);
                $waktuTerimaBpjs = $this->parseWaktuBpjs($bpjs['waktu'] ?? null);

                $selisihDetik = null;
                if ($waktuSirus !== '' && $waktuRsBpjs !== null) {
                    try {
                        $sirus = Carbon::createFromFormat('d/m/Y H:i:s', $waktuSirus, config('app.timezone'));
                        $selisihDetik = (int) $sirus->diffInSeconds($waktuRsBpjs, false);
                    } catch (\Throwable) {
                        $selisihDetik = null;
                    }
                }

                // Lewati task yang kosong di kedua sisi (mis. task 1/2/99 yang memang tak terjadi).
                if ($waktuSirus === '' && $bpjs === null) {
                    continue;
                }

                $this->barisTask[] = [
                    'task' => $n,
                    'label' => TaskIdAntrean::label($n),
                    'tasknameBpjs' => (string) ($bpjs['taskname'] ?? ''),
                    'waktuSirus' => $waktuSirus,
                    'statusKirim' => (string) ($taskSirus['taskId' . $n . 'Status'] ?? ''),
                    'waktuRsBpjs' => $waktuRsBpjs?->format('d/m/Y H:i:s'),
                    'waktuTerimaBpjs' => $waktuTerimaBpjs?->format('d/m/Y H:i:s'),
                    'selisihDetik' => $selisihDetik,
                ];
            }
        } catch (\Exception $e) {
            $this->pesanGagalTask = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }

    /** "08-07-2026 16:11:07 WIB" → Carbon WIB; format lain / kosong → null. */
    private function parseWaktuBpjs(?string $teks): ?Carbon
    {
        $teks = trim(str_ireplace('WIB', '', (string) $teks));
        if ($teks === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('d-m-Y H:i:s', $teks, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    public function closeModal(): void
    {
        $this->identitasTask = [];
        $this->barisTask = [];
        $this->pesanGagalTask = '';
        $this->dispatch('close-modal', name: 'banding-task-id-antrean');
    }

    /* ===============================
     | HELPERS
     =============================== */
    private function isPoliSpesialis($poliId): bool
    {
        return DB::table('rsmst_polis')->where('poli_id', $poliId)->where('spesialis_status', '1')->exists();
    }
};
?>

{{-- Indikator proses global (host tak punya tombol sendiri — tombol ada di baris pelayanan).
     Muncul saat salah satu aksi Task ID sedang diproses. --}}
<div wire:key="task-id-poli-actions-host">
    <div wire:loading wire:target="proses, prosesTaskId4, prosesTaskId5, prosesTaskidAntrean, muatUlangTask"
        class="fixed bottom-4 right-4 z-50 flex items-center gap-2 px-4 py-2.5 text-sm font-medium
               text-white bg-blue-600 rounded-xl shadow-lg dark:bg-blue-500">
        <x-loading />
        Memproses Task ID…
    </div>

    {{-- Modal banding TaskId Antrean: waktu di SIRUS vs yang tercatat di BPJS (getlisttask). Hanya baca. --}}
    <x-modal name="banding-task-id-antrean" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full">
            <x-modul-dokumen.header judul="TaskId Antrean BPJS" jalur="RJ"
                ikon="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                Banding stempel waktu tiap task di SIRUS dengan yang tercatat di Antrean BPJS. "Dikirim RS" = waktu yang
                kita laporkan, "Diterima BPJS" = kapan BPJS menerimanya. Selisih bukan 0 berarti stempel di BPJS tidak
                sama dengan di SIRUS.
            </x-modul-dokumen.header>

            <div class="flex-1 p-4 sm:p-6 space-y-4">
                @if (!empty($identitasTask))
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-muted dark:text-gray-400">
                        <span class="font-semibold text-ink dark:text-white">{{ $identitasTask['regName'] }}
                            <span class="font-mono font-normal text-muted dark:text-gray-400">{{ $identitasTask['regNo'] }}</span></span>
                        <span>{{ $identitasTask['poliDesc'] }} · {{ $identitasTask['drDesc'] }}</span>
                        <span>Tgl: <span class="font-mono text-ink dark:text-gray-200">{{ $identitasTask['rjDate'] ?: '-' }}</span></span>
                        <span>No Booking: <span class="font-mono text-ink dark:text-gray-200">{{ $identitasTask['noBooking'] ?: '-' }}</span></span>
                        @unless ($poliSpesialis)
                            <x-badge variant="warning">Poli non-spesialis: task 3/4/5 tidak dikirim ke BPJS</x-badge>
                        @endunless
                    </div>
                @endif

                @if ($pesanGagalTask !== '')
                    <div class="px-4 py-2.5 text-sm text-red-700 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                        {{ $pesanGagalTask }}
                    </div>
                @endif

                <x-modul-dokumen.tabel-daftar :kolom="['Task', 'Waktu di SIRUS', 'Kode Kirim' => 'text-center', 'Dikirim RS (BPJS)', 'Diterima BPJS', 'Selisih' => 'text-center']">
                    @forelse ($barisTask as $baris)
                        <tbody wire:key="banding-task-{{ $baris['task'] }}" class="border-b border-hairline dark:border-gray-700">
                            <tr class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                <td class="px-4 py-3 align-middle">
                                    <span class="font-mono font-semibold text-ink dark:text-white">{{ $baris['task'] }}</span>
                                    <span class="text-ink dark:text-gray-200">· {{ $baris['label'] }}</span>
                                    @if ($baris['tasknameBpjs'] !== '')
                                        <span class="block text-xs text-muted dark:text-gray-400">{{ $baris['tasknameBpjs'] }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono whitespace-nowrap align-middle text-ink dark:text-gray-200">
                                    {{ $baris['waktuSirus'] ?: '-' }}
                                </td>
                                <td class="px-4 py-3 text-center align-middle">
                                    @if ($baris['statusKirim'] !== '')
                                        <x-badge :variant="in_array($baris['statusKirim'], ['200', '208'], true) ? 'success' : 'danger'">{{ $baris['statusKirim'] }}</x-badge>
                                    @else
                                        <span class="text-xs text-muted-soft dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono whitespace-nowrap align-middle text-ink dark:text-gray-200">
                                    {{ $baris['waktuRsBpjs'] ?? '-' }}
                                </td>
                                <td class="px-4 py-3 font-mono whitespace-nowrap align-middle text-muted dark:text-gray-300">
                                    {{ $baris['waktuTerimaBpjs'] ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center align-middle whitespace-nowrap">
                                    @if ($baris['selisihDetik'] === null)
                                        <span class="text-xs text-muted dark:text-gray-400">{{ $baris['waktuRsBpjs'] ? 'belum di SIRUS' : ($baris['waktuSirus'] ? 'belum di BPJS' : '-') }}</span>
                                    @elseif ($baris['selisihDetik'] === 0)
                                        <x-badge variant="success">Sama</x-badge>
                                    @else
                                        @php
                                            $detikAbs = abs($baris['selisihDetik']);
                                            $teksSelisih = ($baris['selisihDetik'] > 0 ? '+' : '−')
                                                . ($detikAbs >= 3600 ? intdiv($detikAbs, 3600) . ' j ' : '')
                                                . ($detikAbs >= 60 ? intdiv($detikAbs % 3600, 60) . ' m ' : '')
                                                . ($detikAbs % 60) . ' d';
                                        @endphp
                                        <x-badge variant="warning" title="BPJS dikurangi SIRUS">{{ $teksSelisih }}</x-badge>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <x-modul-dokumen.baris-kosong :kolom="6" pesan="Belum ada task yang tercatat di SIRUS maupun di BPJS." />
                    @endforelse
                </x-modul-dokumen.tabel-daftar>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <p class="flex items-center gap-1.5 mr-auto text-sm text-muted dark:text-gray-400">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Hanya membaca — tidak mengirim ulang task. Kode Kirim 200/208 = diterima BPJS.</span>
                    </p>
                    <x-secondary-button type="button" wire:click="muatUlangTask" wire:loading.attr="disabled" wire:target="muatUlangTask">
                        <span wire:loading.remove wire:target="muatUlangTask" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Muat Ulang
                        </span>
                        <span wire:loading wire:target="muatUlangTask" class="inline-flex items-center gap-2"><x-loading /> Memuat...</span>
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
