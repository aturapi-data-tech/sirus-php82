<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\BPJS\AplicaresTrait;
use App\Http\Traits\SIRS\SirsTrait;

new class extends Component {

    use AplicaresTrait, SirsTrait;

    // ─── State ───────────────────────────────────────────────────────────────
    public array $rows      = [];
    public array $logLines  = [];

    // ─── Mount ───────────────────────────────────────────────────────────────
    public function mount(): void
    {
        $this->loadRows();
    }

    // ─── Load rows dari rsmst_rooms ─────────────────────────────────────────
    public function loadRows(): void
    {
        $rooms = DB::table('rsmst_rooms as r')
            ->select('r.room_id', 'r.room_name', 'r.class_id',
                     'r.aplic_kodekelas', 'r.sirs_id_tt', 'r.sirs_id_t_tt',
                     'c.class_desc', 'b.bangsal_name')
            ->leftJoin('rsmst_class as c', 'r.class_id', '=', 'c.class_id')
            ->leftJoin('rsmst_bangsals as b', 'r.bangsal_id', '=', 'b.bangsal_id')
            ->whereIn('r.active_status', ['AC', '1'])
            ->orderBy('b.bangsal_name')
            ->orderBy('r.room_name')
            ->get();

        $this->rows = $rooms->map(function ($room) {
            $kapasitas = DB::table('rsmst_beds')->where('room_id', $room->room_id)->count();
            $terpakai  = DB::table('rstxn_rihdrs')->where('room_id', $room->room_id)->where('ri_status', 'I')->count();

            return [
                'room_id'         => (string) $room->room_id,
                'rs_namabangsal'  => (string) ($room->bangsal_name ?? ''),
                'rs_namakamar'    => (string) ($room->room_name ?? ''),
                'rs_namakelas'    => (string) ($room->class_desc ?? ''),
                'class_id'        => $room->class_id,
                'aplic_kodekelas' => (string) ($room->aplic_kodekelas ?? ''),
                'sirs_id_tt'      => (string) ($room->sirs_id_tt ?? ''),
                'id_t_tt_sirs'    => ($room->sirs_id_t_tt ?? null) ?: null,
                'kapasitas'       => $kapasitas,
                'terpakai'        => $terpakai,
                'tersedia'        => max(0, $kapasitas - $terpakai),
                'status_aplic'    => null,
                'pesan_aplic'     => '',
                'status_sirs'     => null,
                'pesan_sirs'      => '',
            ];
        })->values()->all();
    }

    // ─── Refresh DB ─────────────────────────────────────────────────────────
    public function refresh(): void
    {
        $this->loadRows();
        $this->logLines = [];
        $this->dispatch('toast', type: 'info', message: 'Data DB diperbarui.');
    }


    // =========================================================================
    // APLICARES
    // =========================================================================
    public function syncAplicSatu(int $index): void
    {
        $row = $this->rows[$index] ?? null;
        if (!$row) return;

        if (!$row['aplic_kodekelas']) {
            $this->rows[$index]['status_aplic'] = 'skip';
            $this->rows[$index]['pesan_aplic']  = 'Kode Aplicares belum diisi';
            return;
        }

        $this->rows[$index]['status_aplic'] = 'loading';

        $namaruang = trim($row['rs_namakamar'] . ' ' . $row['rs_namakelas']);
        $payload   = [
            'kodekelas'          => $row['aplic_kodekelas'],
            'koderuang'          => $row['room_id'],
            'namaruang'          => $namaruang,
            'kapasitas'          => $row['kapasitas'] ?: 1,
            'tersedia'           => $row['tersedia'],
            'tersediapria'       => 0,
            'tersediawanita'     => 0,
            'tersediapriawanita' => $row['tersedia'],
        ];

        try {
            $res  = $this->updateKetersediaanTempatTidur($payload)->getOriginalContent();
            $code = $res['metadata']['code'] ?? 500;
            $msg  = $res['metadata']['message'] ?? '-';
            $ok   = $code == 1;

            $this->rows[$index]['status_aplic'] = $ok ? 'ok' : 'error';
            $this->rows[$index]['pesan_aplic']  = $msg;
            $this->addLog('APLIC', $row['rs_namakamar'], $ok ? 'ok' : 'error', $msg);
        } catch (\Throwable $e) {
            $this->rows[$index]['status_aplic'] = 'error';
            $this->rows[$index]['pesan_aplic']  = $e->getMessage();
            $this->addLog('APLIC', $row['rs_namakamar'], 'error', $e->getMessage());
        }
    }

    public function syncAplicSemua(): void
    {
        $this->logLines = [];
        foreach ($this->rows as $i => $_) {
            $this->syncAplicSatu($i);
        }
        $ok = collect($this->rows)->where('status_aplic', 'ok')->count();
        $this->dispatch('toast', type: 'success', message: "Kirim ke Aplicares selesai: {$ok} berhasil.");
    }

    // =========================================================================
    // SIRS
    // =========================================================================
    public function syncSirsSatu(int $index): void
    {
        $row = $this->rows[$index] ?? null;
        if (!$row) return;

        if (!$row['sirs_id_tt']) {
            $this->rows[$index]['status_sirs'] = 'skip';
            $this->rows[$index]['pesan_sirs']  = 'id_tt SIRS belum diisi';
            return;
        }

        $this->rows[$index]['status_sirs'] = 'loading';

        $namaRuang = trim($row['rs_namakamar'] . ' ' . $row['rs_namakelas']);
        $payload   = [
            'ruang'               => $namaRuang,
            'jumlah_ruang'        => 1,
            'jumlah'              => $row['kapasitas'] ?: 1,
            'terpakai'            => $row['terpakai'],
            'terpakai_suspek'     => 0,
            'terpakai_konfirmasi' => 0,
            'antrian'             => 0,
            'prepare'             => 0,
            'prepare_plan'        => 0,
            'covid'               => 0,
        ];

        try {
            if ($row['id_t_tt_sirs']) {
                // PUT — sudah punya id_t_tt
                $res    = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $row['id_t_tt_sirs']]))->getOriginalContent();
                $first  = $res['fasyankes'][0] ?? [];
                $status = (string) ($first['status'] ?? '500');
                $ok     = $status === '200';
                $msg    = $first['message'] ?? '-';
            } else {
                // POST — daftar baru
                $res    = $this->sirsKirimTempaTidur(array_merge($payload, ['id_tt' => $row['sirs_id_tt']]))->getOriginalContent();
                $first  = $res['fasyankes'][0] ?? [];
                $status = (string) ($first['status'] ?? '500');
                $msg    = $first['message'] ?? '-';

                if ($status === '200' && !str_contains($msg, 'sudah ada')) {
                    $idTTt = (string) ($first['id_t_tt'] ?? '');
                    if ($idTTt) {
                        $this->rows[$index]['id_t_tt_sirs'] = $idTTt;
                        DB::table('rsmst_rooms')->where('room_id', $row['room_id'])->update(['sirs_id_t_tt' => $idTTt]);
                    }
                    $ok = true;
                } elseif ($status === '200' && str_contains($msg, 'sudah ada')) {
                    // Auto-GET → PUT
                    $listRes = $this->sirsGetTempaTidur()->getOriginalContent();
                    $match   = collect($listRes['fasyankes'] ?? [])->first(fn($r) =>
                        (string) ($r['id_tt'] ?? '') === (string) $row['sirs_id_tt'] &&
                        ($r['id_t_tt'] ?? null) !== null
                    );

                    if ($match) {
                        $idTTt  = (string) $match['id_t_tt'];
                        $this->rows[$index]['id_t_tt_sirs'] = $idTTt;
                        DB::table('rsmst_rooms')->where('room_id', $row['room_id'])->update(['sirs_id_t_tt' => $idTTt]);

                        $resU   = $this->sirsUpdateTempaTidur(array_merge($payload, ['id_t_tt' => $idTTt]))->getOriginalContent();
                        $firstU = $resU['fasyankes'][0] ?? [];
                        $statU  = (string) ($firstU['status'] ?? '500');
                        $ok     = $statU === '200';
                        $msg    = $ok ? 'Sudah ada, berhasil diperbarui' : ($firstU['message'] ?? 'Gagal');
                    } else {
                        $ok  = null; // warning
                        $msg = 'Sudah ada di SIRS, id_t_tt tidak ditemukan';
                    }
                } else {
                    $ok = false;
                }
            }

            $this->rows[$index]['status_sirs'] = $ok === true ? 'ok' : ($ok === null ? 'warning' : 'error');
            $this->rows[$index]['pesan_sirs']  = $msg;
            $this->addLog('SIRS', $row['rs_namakamar'], $ok === true ? 'ok' : 'error', $msg);
        } catch (\Throwable $e) {
            $this->rows[$index]['status_sirs'] = 'error';
            $this->rows[$index]['pesan_sirs']  = $e->getMessage();
            $this->addLog('SIRS', $row['rs_namakamar'], 'error', $e->getMessage());
        }
    }

    public function syncSirsSemua(): void
    {
        $this->logLines = [];
        foreach ($this->rows as $i => $_) {
            $this->syncSirsSatu($i);
        }
        $ok = collect($this->rows)->where('status_sirs', 'ok')->count();
        $this->dispatch('toast', type: 'success', message: "Kirim ke SIRS selesai: {$ok} berhasil.");
    }

    // ─── Helper ─────────────────────────────────────────────────────────────
    private function addLog(string $sistem, string $kamar, string $status, string $msg): void
    {
        $this->logLines[] = [
            'waktu'  => now()->format('H:i:s'),
            'sistem' => $sistem,
            'kamar'  => $kamar,
            'status' => $status,
            'msg'    => $msg,
        ];
    }
};
?>

<div class="p-4 space-y-4">

    {{-- ══ HEADER ══════════════════════════════════════════════════════════ --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-gray-100">Update Tempat Tidur RI</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Kirim ketersediaan kamar rawat inap ke Aplicares BPJS & SIRS Kemenkes secara real-time.
            </p>
        </div>
        <x-secondary-button wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh" class="shrink-0 gap-2">
            <x-loading size="xs" wire:loading wire:target="refresh" />
            <svg wire:loading.remove wire:target="refresh" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582M20 20v-5h-.581M4.582 9A7.001 7.001 0 0112 5c2.276 0 4.293.965 5.71 2.5M19.418 15A7.001 7.001 0 0112 19c-2.276 0-4.293-.965-5.71-2.5"/>
            </svg>
            Refresh DB
        </x-secondary-button>
    </div>

    {{-- ══ RINGKASAN ════════════════════════════════════════════════════════ --}}
    @php
        $totalKap  = collect($rows)->sum('kapasitas');
        $totalTerp = collect($rows)->sum('terpakai');
        $totalTers = collect($rows)->sum('tersedia');
        $totalOcc  = $totalKap > 0 ? round($totalTerp / $totalKap * 100) : 0;
    @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-center">
            <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $totalKap }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Total Kapasitas</div>
        </div>
        <div class="p-3 bg-rose-50 dark:bg-rose-900/20 rounded-xl border border-rose-200 dark:border-rose-800 text-center">
            <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ $totalTerp }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Terisi</div>
        </div>
        <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800 text-center">
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $totalTers }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tersedia</div>
        </div>
        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800 text-center">
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $totalOcc }}%</div>
            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Occupancy</div>
        </div>
    </div>

    {{-- ══ TAB + KONTEN ════════════════════════════════════════════════════ --}}
    <div x-data="{ tab: 'aplicares' }">

        {{-- Tab bar --}}
        <div class="flex border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <button type="button" @click="tab = 'aplicares'"
                :class="tab === 'aplicares'
                    ? 'border-b-2 border-brand text-brand dark:text-brand-lime dark:border-brand-lime font-semibold'
                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                class="px-5 py-3 text-sm transition-colors flex items-center gap-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                             bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS</span>
                Aplicares
            </button>
            <button type="button" @click="tab = 'sirs'"
                :class="tab === 'sirs'
                    ? 'border-b-2 border-brand text-brand dark:text-brand-lime dark:border-brand-lime font-semibold'
                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                class="px-5 py-3 text-sm transition-colors flex items-center gap-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold
                             bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS</span>
                Kemenkes
            </button>
        </div>

        {{-- ── TAB APLICARES ────────────────────────────────────────────── --}}
        <div x-show="tab === 'aplicares'" class="space-y-3 pt-4">
            <div class="flex items-center justify-between gap-2">
                @php
                    $aplOk   = collect($rows)->where('status_aplic', 'ok')->count();
                    $aplFail = collect($rows)->where('status_aplic', 'error')->count();
                    $aplSkip = collect($rows)->where('status_aplic', 'skip')->count();
                @endphp
                @if ($aplOk || $aplFail || $aplSkip)
                    <div class="flex items-center gap-3 text-xs">
                        @if ($aplOk) <span class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $aplOk }} ok</span> @endif
                        @if ($aplFail) <span class="text-red-600 dark:text-red-400 font-mono font-semibold">{{ $aplFail }} gagal</span> @endif
                        @if ($aplSkip) <span class="text-gray-400 dark:text-gray-500 font-mono">{{ $aplSkip }} dilewati</span> @endif
                    </div>
                @else
                    <span></span>
                @endif
                <x-primary-button wire:click="syncAplicSemua" wire:loading.attr="disabled"
                    wire:target="syncAplicSemua" wire:confirm="Kirim semua kamar ke Aplicares BPJS?" class="gap-2">
                    <x-loading size="xs" wire:loading wire:target="syncAplicSemua" />
                    <svg wire:loading.remove wire:target="syncAplicSemua" class="w-4 h-4"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    Kirim Semua ke Aplicares
                </x-primary-button>
            </div>
            @include('pages.transaksi.ri.update-tt-ri.table-aplicares')
        </div>

        {{-- ── TAB SIRS ──────────────────────────────────────────────────── --}}
        <div x-show="tab === 'sirs'" class="space-y-3 pt-4">
            <div class="flex items-center justify-between gap-2">
                @php
                    $srsOk   = collect($rows)->where('status_sirs', 'ok')->count();
                    $srsFail = collect($rows)->where('status_sirs', 'error')->count();
                    $srsSkip = collect($rows)->where('status_sirs', 'skip')->count();
                @endphp
                @if ($srsOk || $srsFail || $srsSkip)
                    <div class="flex items-center gap-3 text-xs">
                        @if ($srsOk) <span class="text-emerald-600 dark:text-emerald-400 font-mono font-semibold">{{ $srsOk }} ok</span> @endif
                        @if ($srsFail) <span class="text-red-600 dark:text-red-400 font-mono font-semibold">{{ $srsFail }} gagal</span> @endif
                        @if ($srsSkip) <span class="text-gray-400 dark:text-gray-500 font-mono">{{ $srsSkip }} dilewati</span> @endif
                    </div>
                @else
                    <span></span>
                @endif
                <div class="flex items-center gap-2">
                    <x-primary-button wire:click="syncSirsSemua" wire:loading.attr="disabled"
                        wire:target="syncSirsSemua" wire:confirm="Kirim semua kamar ke SIRS Kemenkes?" class="gap-2">
                        <x-loading size="xs" wire:loading wire:target="syncSirsSemua" />
                        <svg wire:loading.remove wire:target="syncSirsSemua" class="w-4 h-4"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Kirim Semua ke SIRS
                    </x-primary-button>
                </div>
            </div>

            @include('pages.transaksi.ri.update-tt-ri.table-sirs')
        </div>

    </div>{{-- end x-data tab --}}

    {{-- ══ LOG SYNC ═════════════════════════════════════════════════════════ --}}
    @if (!empty($logLines))
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">
                    Log Aktivitas
                </span>
                <button wire:click="$set('logLines', [])" class="text-xs text-gray-400 hover:text-red-500 transition">
                    Hapus Log
                </button>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700 max-h-52 overflow-y-auto">
                @foreach (array_reverse($logLines) as $log)
                    <div class="flex items-center gap-3 px-4 py-2 text-sm">
                        <span class="font-mono text-xs text-gray-400 shrink-0 w-14">{{ $log['waktu'] }}</span>
                        <span class="shrink-0">
                            @if ($log['sistem'] === 'APLIC')
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">BPJS</span>
                            @else
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">SIRS</span>
                            @endif
                        </span>
                        <span class="font-semibold text-gray-700 dark:text-gray-300 shrink-0 truncate max-w-[120px]">{{ $log['kamar'] }}</span>
                        @if ($log['status'] === 'ok')
                            <span class="text-emerald-600 dark:text-emerald-400 shrink-0">&#10003;</span>
                        @else
                            <span class="text-red-500 shrink-0">&#10007;</span>
                        @endif
                        <span class="text-gray-500 dark:text-gray-400 truncate">{{ $log['msg'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
