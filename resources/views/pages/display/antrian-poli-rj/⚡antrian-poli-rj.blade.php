<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new #[Layout('layouts.app-fullscreen')] class extends Component {
    /**
     * ════════════════════════════════════════════════════════════════════════
     *  DISPLAY PUBLIK — ANTRIAN POLI RAWAT JALAN HARI INI
     * ════════════════════════════════════════════════════════════════════════
     *
     *  Layar TV ruang tunggu RJ: per poli + dokter, nomor antrian yang SEDANG
     *  DIPERIKSA, nomor berikutnya, dan seluruh nomor antrian hari ini beserta
     *  tahapnya. Pengganti displayPelayananRJ di SIRUS lama; gaya mengikuti
     *  /display/jadwal-poli.
     *
     *  SUMBER: rsview_rjkasir, rj_date = hari ini, klaim_id != 'KR', rj_status A/L.
     *
     *  TAHAP PASIEN (urutan task ID Antrean BPJS)
     *  ──────────────────────────────────────────
     *  • Selesai   = keluar poli (taskId5 terisi) ATAU sudah masuk apotek
     *                (waktu_masuk_apt) ATAU rj_status 'L'
     *  • Diperiksa = sudah masuk poli (waktu_masuk_poli = taskId4) dan belum selesai
     *  • Menunggu  = belum masuk poli
     *  taskId5 hanya ada di JSON → penanda 0/1 dihitung di SQL dengan DBMS_LOB.INSTR
     *  (pola Antrian Kasir RJ), CLOB tidak ikut diambil.
     *
     *  "SEDANG DIPERIKSA" = pasien Diperiksa dengan waktu masuk poli TERBARU
     *  (bila petugas lupa mencatat keluar poli, pasien sebelumnya tetap Diperiksa
     *  tetapi nomor besar menunjuk panggilan terakhir).
     *
     *  PRIVASI (halaman publik, tanpa login): hanya nomor antrian; nama pasien
     *  disamarkan ("SITI A*** R***") dan hanya untuk yang sedang diperiksa.
     *  Tidak ada No. RM, alamat, atau isi EMR.
     * ════════════════════════════════════════════════════════════════════════
     */
    public string $myTitle = 'Antrian Poli Rawat Jalan';

    public function placeholder(): string
    {
        return '<div class="p-8 text-center text-muted-soft">Memuat antrian…</div>';
    }

    /**
     * "SITI AMINAH RAHAYU" → "SITI A*** R***" — kata pertama utuh supaya dikenali sendiri; kata berikutnya
     * huruf pertama + 3 bintang TETAP (bukan sepanjang kata) agar panjang nama asli tidak terbaca.
     */
    private function samarkanNama(?string $nama): string
    {
        $kataList = preg_split('/\s+/', trim((string) $nama), -1, PREG_SPLIT_NO_EMPTY);
        if ($kataList === []) {
            return '';
        }
        $depan = array_shift($kataList);
        $inisial = array_map(fn($kata) => mb_substr($kata, 0, 1) . '***', $kataList);

        return trim($depan . ' ' . implode(' ', $inisial));
    }

    public function with(): array
    {
        $now = Carbon::now(env('APP_TIMEZONE'));
        $today = $now->format('Y-m-d');

        $keluarPoli = DB::raw("CASE WHEN DBMS_LOB.INSTR(datadaftarpolirj_json, '\"taskId5\":\"') > 0 AND DBMS_LOB.INSTR(datadaftarpolirj_json, '\"taskId5\":\"\"') = 0 THEN 1 ELSE 0 END AS keluar_poli");

        $pasienList = DB::table('rsview_rjkasir')
            ->select([
                'poli_id',
                'poli_desc',
                'dr_id',
                'dr_name',
                'no_antrian',
                'reg_name',
                'rj_status',
                DB::raw("to_char(waktu_masuk_poli, 'yyyymmddhh24miss') AS masuk_poli"),
                DB::raw("to_char(waktu_masuk_apt, 'yyyymmddhh24miss') AS masuk_apt"),
                DB::raw("to_char(rj_date, 'yyyymmddhh24miss') AS waktu_daftar"),
                $keluarPoli,
            ])
            ->where(DB::raw("to_char(rj_date,'yyyy-mm-dd')"), $today)
            ->where('klaim_id', '!=', 'KR')
            ->whereIn(DB::raw("nvl(rj_status,'A')"), ['A', 'L'])
            ->get()
            // DTO bersih: nama lengkap tidak ikut keluar dari sini — hanya bentuk samaran, dan hanya untuk
            // pasien yang sedang di poli (satu-satunya yang namanya tampil). Data ini dipakai saat render
            // saja (with()), bukan properti publik, jadi tidak ikut snapshot Livewire di browser.
            ->map(function ($row) {
                $selesai = (int) $row->keluar_poli === 1 || !empty($row->masuk_apt) || ($row->rj_status ?? '') === 'L';
                $tahap = $selesai ? 'selesai' : (!empty($row->masuk_poli) ? 'diperiksa' : 'menunggu');

                return [
                    'kunci' => $row->poli_id . '|' . $row->dr_id,
                    'poli_desc' => $row->poli_desc,
                    'dr_name' => $row->dr_name,
                    'no_antrian' => $row->no_antrian !== null && $row->no_antrian !== '' ? (int) $row->no_antrian : null,
                    'masuk_poli' => (string) ($row->masuk_poli ?? ''),
                    'waktu_daftar' => (string) ($row->waktu_daftar ?? ''),
                    'nama_samar' => $tahap === 'diperiksa' ? $this->samarkanNama($row->reg_name) : '',
                    'tahap' => $tahap,
                ];
            });

        // Jam praktik dari jadwal hari ini (scmst_scpolis), hanya untuk keterangan di kartu.
        $jadwalPerDokter = DB::table('scmst_scpolis')
            ->where('day_id', $now->dayOfWeekIso)
            ->where('sc_poli_status_', '1')
            ->select('poli_id', 'dr_id', 'mulai_praktek', 'selesai_praktek')
            ->get()
            ->keyBy(fn($jadwal) => $jadwal->poli_id . '|' . $jadwal->dr_id);

        $urutAntrian = fn($pasien) => [$pasien['no_antrian'] === null ? 1 : 0, $pasien['no_antrian'] ?? 0, $pasien['waktu_daftar']];

        $kartuList = $pasienList
            ->groupBy('kunci')
            ->map(function ($pasienDokter, $kunci) use ($jadwalPerDokter, $urutAntrian) {
                $pertama = $pasienDokter->first();
                $antrianUrut = $pasienDokter->sortBy($urutAntrian)->values();

                $sedangDiperiksa = $pasienDokter->where('tahap', 'diperiksa')->sortByDesc('masuk_poli')->first();
                $jadwal = $jadwalPerDokter->get($kunci);

                return [
                    'poli_desc' => $pertama['poli_desc'],
                    'dr_name' => $pertama['dr_name'] ?: '(dokter belum dipetakan)',
                    'jam' => $jadwal ? substr((string) $jadwal->mulai_praktek, 0, 5) . ' – ' . substr((string) $jadwal->selesai_praktek, 0, 5) : '',
                    'sedang' => $sedangDiperiksa,
                    'berikutnya' => $antrianUrut->where('tahap', 'menunggu')->take(5)->values(),
                    'antrian' => $antrianUrut,
                    'n_menunggu' => $pasienDokter->where('tahap', 'menunggu')->count(),
                    'n_diperiksa' => $pasienDokter->where('tahap', 'diperiksa')->count(),
                    'n_selesai' => $pasienDokter->where('tahap', 'selesai')->count(),
                ];
            })
            // Kartu yang masih ada antrian di depan, yang sudah selesai semua di belakang.
            ->sortBy(fn($kartu) => [$kartu['n_menunggu'] + $kartu['n_diperiksa'] > 0 ? 0 : 1, $kartu['poli_desc'], $kartu['dr_name']])
            ->values();

        return [
            'kartuList' => $kartuList,
            'totalDokter' => $kartuList->count(),
            'totalPasien' => $pasienList->count(),
            'totalMenunggu' => $pasienList->where('tahap', 'menunggu')->count(),
            'totalDiperiksa' => $pasienList->where('tahap', 'diperiksa')->count(),
            'totalSelesai' => $pasienList->where('tahap', 'selesai')->count(),
            'hariIni' => $now->locale('id')->isoFormat('dddd, D MMMM YYYY'),
            'jamNow' => $now->format('H:i:s'),
        ];
    }
};
?>

<div class="relative overflow-hidden bg-canvas h-screen" wire:poll.15s.keep-alive>

    {{-- Watermark logogram brand (pola jadwal-poli) --}}
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
        <img src="{{ asset('images/Logogram black solid.png') }}" alt=""
            class="absolute -right-[6%] -bottom-[12%] w-[34rem] opacity-5">
    </div>

    <div class="relative z-10 flex flex-col h-full w-full px-4 pt-4 pb-2"
        x-data="autoScroller({ step: 1, interval: 25, waitTop: 800, waitBottom: 1200 })" x-init="start()">

        {{-- Header — TITLE kiri · STATS tengah · JAM kanan --}}
        <div class="flex flex-wrap items-stretch justify-between gap-3 mb-3">
            <div class="shrink-0 flex items-center gap-3">
                <img src="{{ asset('images/Logo Persegi.png') }}" alt="RSI Madinah" class="h-16 w-auto shrink-0">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-brand-green leading-tight">{{ $myTitle }}</h1>
                    <p class="text-sm text-body capitalize">{{ $hariIni }}</p>
                    <div class="mt-1.5 h-0.5 w-28 bg-brand-lime"></div>
                </div>
            </div>

            <div class="flex-1 min-w-[280px] flex flex-col items-end justify-center gap-0.5 px-3 py-1.5">
                <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-0.5 text-base text-muted">
                    <span>Dokter <span class="font-bold font-mono text-body-strong">{{ $totalDokter }}</span></span>
                    <span class="text-muted-soft">·</span>
                    <span>Pasien <span class="font-bold font-mono text-body-strong">{{ $totalPasien }}</span></span>
                    <span class="text-muted-soft">·</span>
                    <span>Menunggu <span class="font-bold font-mono text-body-strong">{{ $totalMenunggu }}</span></span>
                    <span class="text-muted-soft">·</span>
                    <span>Diperiksa <span class="font-bold font-mono text-body-strong">{{ $totalDiperiksa }}</span></span>
                    <span class="text-muted-soft">·</span>
                    <span>Selesai <span class="font-bold font-mono text-body-strong">{{ $totalSelesai }}</span></span>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-0.5 text-sm text-muted-soft">
                    <span class="font-semibold uppercase tracking-wide">Ket:</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded border border-hairline bg-surface-elevated"></span>Menunggu</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-brand-green"></span>Diperiksa</span>
                    <span class="inline-flex items-center gap-1"><span class="inline-block w-3 h-3 rounded bg-hairline"></span>Selesai</span>
                </div>
            </div>

            <div class="shrink-0 text-right self-center">
                <div class="text-sm text-muted">Sekarang</div>
                <div class="text-xl font-mono font-semibold text-body-strong">{{ $jamNow }}</div>
            </div>
        </div>

        {{-- Grid kartu per poli + dokter --}}
        <div class="flex-1 min-h-0 overflow-auto" x-ref="scroller" x-on:mouseenter="pause()" x-on:mouseleave="resume()">

            @if ($kartuList->isEmpty())
                <div class="w-full p-8 text-center text-sm text-muted">
                    Belum ada pasien terdaftar di poli hari ini.
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-2">
                    @foreach ($kartuList as $kartu)
                        @php
                            $semuaSelesai = $kartu['n_menunggu'] + $kartu['n_diperiksa'] === 0;
                            $cardBg = $semuaSelesai
                                ? 'bg-surface-soft border-hairline'
                                : ($kartu['sedang']
                                    ? 'bg-surface-elevated border-brand-green/40 ring-1 ring-brand-green/10'
                                    : 'bg-surface-elevated border-hairline');
                        @endphp
                        <div class="rounded-lg border {{ $cardBg }} overflow-hidden flex flex-col">
                            {{-- Header kartu: poli + jam praktik --}}
                            <div class="flex items-center justify-between gap-2 px-3 py-1.5 border-b border-hairline-soft">
                                <h3 class="text-[11px] font-bold uppercase tracking-wide text-body-strong truncate"
                                    title="{{ $kartu['poli_desc'] }}">
                                    {{ $kartu['poli_desc'] ?: 'POLI -' }}
                                </h3>
                                @if ($kartu['jam'] !== '')
                                    <span class="shrink-0 text-[11px] font-mono text-muted">{{ $kartu['jam'] }}</span>
                                @endif
                            </div>

                            <div class="px-3 py-2 flex flex-col gap-2 flex-1">
                                <div class="text-sm font-semibold leading-tight truncate {{ $semuaSelesai ? 'text-muted' : 'text-ink' }}"
                                    title="{{ $kartu['dr_name'] }}">
                                    {{ $kartu['dr_name'] }}
                                </div>

                                {{-- Sedang diperiksa --}}
                                <div class="flex items-center gap-3 rounded-md px-3 py-2 {{ $kartu['sedang'] ? 'bg-brand-green text-white' : 'bg-surface-soft text-muted' }}">
                                    <div class="text-center shrink-0">
                                        <div class="text-[10px] uppercase tracking-wider font-bold leading-tight {{ $kartu['sedang'] ? 'text-white/80' : 'text-muted' }}">
                                            Diperiksa
                                        </div>
                                        <div class="text-4xl font-bold font-mono leading-none mt-1">
                                            {{ $kartu['sedang']['no_antrian'] ?? '–' }}
                                        </div>
                                    </div>
                                    <div class="min-w-0 text-sm font-semibold leading-tight truncate">
                                        @if ($kartu['sedang'])
                                            {{ $kartu['sedang']['nama_samar'] }}
                                        @elseif ($semuaSelesai)
                                            Pelayanan selesai
                                        @else
                                            Belum ada pasien masuk
                                        @endif
                                    </div>
                                </div>

                                {{-- Berikutnya --}}
                                <div>
                                    <div class="text-[10px] uppercase tracking-wider text-muted font-bold">Berikutnya</div>
                                    <div class="flex flex-wrap gap-1 mt-0.5">
                                        @forelse ($kartu['berikutnya'] as $pasien)
                                            <span class="px-2 py-0.5 rounded border border-hairline bg-surface-elevated text-base font-bold font-mono text-body-strong">
                                                {{ $pasien['no_antrian'] ?? '–' }}
                                            </span>
                                        @empty
                                            <span class="text-sm text-muted-soft">–</span>
                                        @endforelse
                                    </div>
                                </div>

                                {{-- Seluruh antrian hari ini --}}
                                <div class="pt-1.5 border-t border-hairline-soft">
                                    <div class="text-[10px] uppercase tracking-wider text-muted font-bold">Antrian hari ini</div>
                                    <div class="flex flex-wrap gap-1 mt-0.5">
                                        @foreach ($kartu['antrian'] as $pasien)
                                            @php
                                                $chipClass = match ($pasien['tahap']) {
                                                    'diperiksa' => 'bg-brand-green text-white border-brand-green',
                                                    'selesai' => 'bg-hairline text-muted border-hairline line-through',
                                                    default => 'bg-surface-elevated text-body-strong border-hairline',
                                                };
                                            @endphp
                                            <span class="min-w-[2rem] px-1.5 py-0.5 rounded border text-center text-xs font-mono font-semibold {{ $chipClass }}">
                                                {{ $pasien['no_antrian'] ?? '–' }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Hitungan --}}
                                <div class="grid grid-cols-3 gap-0.5 mt-auto pt-1.5 border-t border-hairline-soft text-center">
                                    <div>
                                        <div class="text-[10px] uppercase tracking-wider text-muted font-bold">Menunggu</div>
                                        <div class="text-lg font-bold font-mono {{ $kartu['n_menunggu'] > 0 ? 'text-warning-deep' : 'text-muted-soft' }}">{{ $kartu['n_menunggu'] }}</div>
                                    </div>
                                    <div class="border-l border-hairline-soft">
                                        <div class="text-[10px] uppercase tracking-wider text-muted font-bold">Diperiksa</div>
                                        <div class="text-lg font-bold font-mono {{ $kartu['n_diperiksa'] > 0 ? 'text-brand-green' : 'text-muted-soft' }}">{{ $kartu['n_diperiksa'] }}</div>
                                    </div>
                                    <div class="border-l border-hairline-soft">
                                        <div class="text-[10px] uppercase tracking-wider text-muted font-bold">Selesai</div>
                                        <div class="text-lg font-bold font-mono {{ $kartu['n_selesai'] > 0 ? 'text-body-strong' : 'text-muted-soft' }}">{{ $kartu['n_selesai'] }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        {{-- Footer --}}
        <div class="relative h-px bg-hairline mt-2">
            <div class="absolute left-0 -top-px h-0.5 w-20 bg-brand-lime"></div>
        </div>
        <div class="mt-1.5 flex items-center justify-between text-[11px] text-muted-soft">
            <span>SIRus · Antrian Poli RJ · diperbarui tiap 15 detik</span>
            <span>© {{ date('Y') }} RSI Madinah</span>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            // definisi sama dengan jadwal-poli / antrian-apotek-rj; lewati bila sudah ada
            if (Alpine.$data?.autoScroller) return;
            Alpine.data('autoScroller', (opts = {}) => ({
                step: opts.step ?? 1,
                interval: opts.interval ?? 25,
                waitTop: opts.waitTop ?? 800,
                waitBottom: opts.waitBottom ?? 1200,
                timer: null,
                running: false,
                start() {
                    const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
                    if (mql.matches) return;
                    this.running = true;
                    this.scrollLoop(true);
                },
                pause() {
                    this.running = false;
                    if (this.timer) {
                        clearTimeout(this.timer);
                        this.timer = null;
                    }
                },
                resume(fromTop = false) {
                    if (this.running) return;
                    this.running = true;
                    this.scrollLoop(fromTop);
                },
                scrollLoop(fromTop = false) {
                    if (!this.running) return;
                    const el = this.$refs.scroller;
                    if (!el) return;
                    if (fromTop) this.timer = setTimeout(() => this.tick(), this.waitTop);
                    else this.tick();
                },
                tick() {
                    if (!this.running) return;
                    const el = this.$refs.scroller;
                    if (!el) return;
                    const atBottom = Math.ceil(el.scrollTop + el.clientHeight) >= el.scrollHeight;
                    if (atBottom) {
                        this.timer = setTimeout(() => {
                            el.scrollTop = 0;
                            this.timer = setTimeout(() => {
                                if (this.running) this.tick();
                            }, this.waitTop);
                        }, this.waitBottom);
                        return;
                    }
                    el.scrollTop += this.step;
                    this.timer = setTimeout(() => this.tick(), this.interval);
                }
            }));
        });
    </script>
</div>
