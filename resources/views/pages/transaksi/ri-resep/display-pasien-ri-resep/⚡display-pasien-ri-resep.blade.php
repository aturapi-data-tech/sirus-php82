<?php
// resources/views/pages/transaksi/ri-resep/display-pasien-ri-resep/display-pasien-ri-resep.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Display pasien khusus RI-Resep (Apotek RI).
 *
 * Beda dari display-pasien-ri: komponen ini SATU SELECT langsung ke tabel —
 * tidak membaca CLOB JSON RI dan tidak menghitung Risiko Jatuh / C-SSRS, karena
 * layar apotek hanya butuh identitas pasien, dokter pemberi resep, dan kamar.
 * Tampilannya sengaja dibuat seirama dengan display-pasien-ri.
 */
new class extends Component {
    public ?int $slsNo = null;
    public array $data = [];

    public function mount(): void
    {
        if ($this->slsNo) {
            $this->load($this->slsNo);
        }
    }

    #[On('ri-resep-display-pasien.refresh')]
    public function refresh(?int $slsNo = null): void
    {
        $this->load($slsNo ?? $this->slsNo);
    }

    public function load(?int $slsNo): void
    {
        $this->data = [];

        if (!$slsNo) {
            return;
        }

        $this->slsNo = $slsNo;

        $row = DB::table('imtxn_slshdrs as s')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 's.reg_no')
            ->leftJoin('rsmst_doctors as d', 'd.dr_id', '=', 's.dr_id')
            ->leftJoin('rstxn_rihdrs as r', 'r.rihdr_no', '=', 's.rihdr_no')
            ->leftJoin('rsmst_klaimtypes as k', 'k.klaim_id', '=', 'r.klaim_id')
            ->leftJoin('rsmst_rooms as rm', 'rm.room_id', '=', 'r.room_id')
            ->leftJoin('rsmst_bangsals as b', 'b.bangsal_id', '=', 'rm.bangsal_id')
            ->select(
                's.sls_no',
                's.rihdr_no',
                's.status',
                DB::raw("to_char(s.sls_date,'dd/mm/yyyy hh24:mi:ss') as sls_date_display"),
                'p.reg_no',
                'p.reg_name',
                'p.sex',
                'p.address',
                'p.rt',
                'p.rw',
                'p.phone',
                'p.nik_bpjs',
                'p.nokartu_bpjs',
                DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date_display"),
                DB::raw("to_char(p.birth_date,'yyyy-mm-dd') as birth_date_raw"),
                'd.dr_name',
                'r.ri_status',
                'k.klaim_desc',
                'k.klaim_status',
                'r.klaim_id',
                'rm.room_name',
                'b.bangsal_name',
            )
            ->where('s.sls_no', $slsNo)
            ->first();

        if (!$row) {
            return;
        }

        // Umur dihitung dari birth_date — kolom thn/bln/hari di master hanya snapshot
        // saat pendaftaran dan tidak pernah di-refresh.
        $umur = '-';
        if (!empty($row->birth_date_raw)) {
            $umur = Carbon::parse($row->birth_date_raw)->diff(Carbon::now())->format('%y Thn %m Bln %d Hr');
        }

        // Alamat + RT/RW dirangkai sekali di sini supaya blade tidak penuh kondisi.
        $alamat = trim((string) ($row->address ?? ''));
        $rt = trim((string) ($row->rt ?? ''));
        $rw = trim((string) ($row->rw ?? ''));
        if ($rt !== '' || $rw !== '') {
            $alamat = trim($alamat . ' RT ' . ($rt ?: '-') . '/RW ' . ($rw ?: '-'));
        }

        $this->data = [
            'slsNo' => $row->sls_no,
            'riHdrNo' => $row->rihdr_no,
            'slsDate' => $row->sls_date_display,
            'status' => $row->status ?: 'A',
            'regNo' => $row->reg_no,
            'regName' => $row->reg_name,
            'sexDesc' => match (strtoupper((string) $row->sex)) {
                'L', '1' => 'Laki-laki',
                'P', '2' => 'Perempuan',
                default => '-',
            },
            'umur' => $umur,
            'tglLahir' => $row->birth_date_display ?: '-',
            'alamat' => $alamat,
            'phone' => $row->phone,
            'nik' => $row->nik_bpjs,
            'noBpjs' => $row->nokartu_bpjs,
            'drName' => $row->dr_name ?: '-',
            'riStatus' => $row->ri_status,
            'klaimDesc' => $row->klaim_desc ?: ($row->klaim_id ?: '-'),
            'klaimStatus' => $row->klaim_status,
            'klaimId' => $row->klaim_id,
            'roomName' => $row->room_name ?: '-',
            'bangsalName' => $row->bangsal_name ?: '-',
        ];
    }
}; ?>

<div>
    @if (!empty($data))
        @php
            $badgeKlaim = ($data['klaimStatus'] ?? '') === 'BPJS' || ($data['klaimId'] ?? '') === 'JM' ? 'info' : 'gray';
        @endphp

        <div class="px-4 py-3 text-base leading-relaxed border border-hairline rounded-lg bg-canvas dark:bg-gray-900">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-x-6 gap-y-2.5">

                {{-- ===== KIRI: Identifikasi Pasien ===== --}}
                <div class="lg:col-span-3 space-y-2 lg:border-r lg:border-hairline dark:lg:border-gray-700 lg:pr-4">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-xl font-bold text-ink dark:text-white">{{ $data['regName'] ?? '-' }}</span>
                        <span
                            class="font-mono text-base text-muted dark:text-gray-400 shrink-0">{{ $data['regNo'] ?? '-' }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5">
                        {{-- Demografi --}}
                        <div class="space-y-1">
                            <div>
                                <span class="text-muted">Jenis Kelamin:</span>
                                <span class="ml-1 text-body dark:text-gray-300">{{ $data['sexDesc'] }}</span>
                            </div>
                            <div>
                                <span class="text-muted">Umur:</span>
                                <span class="ml-1 text-body dark:text-gray-300">{{ $data['umur'] }}</span>
                            </div>
                            <div>
                                <span class="text-muted">Tgl Lahir:</span>
                                <span class="ml-1 text-body dark:text-gray-300">{{ $data['tglLahir'] }}</span>
                            </div>
                        </div>

                        {{-- Kontak & identitas --}}
                        <div class="space-y-1">
                            @if (!empty($data['alamat']))
                                <div class="text-body dark:text-gray-300">📍 {{ $data['alamat'] }}</div>
                            @endif
                            @if (!empty($data['phone']))
                                <div class="text-body dark:text-gray-300">📞 {{ $data['phone'] }}</div>
                            @endif
                            <div class="text-xs font-mono text-muted dark:text-gray-400">
                                🆔 NIK: {{ $data['nik'] ?: '-' }}
                                @if (!empty($data['noBpjs']))
                                    • BPJS: {{ $data['noBpjs'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== KANAN: Info Resep (fokus: dokter & kamar) ===== --}}
                <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">

                    {{-- Sub-kolom kiri: klaim → bangsal/kamar → nomor & waktu resep --}}
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-muted">Jenis Klaim:</span>
                            <x-badge :variant="$badgeKlaim">{{ $data['klaimDesc'] }}</x-badge>
                        </div>

                        <div>
                            <p class="font-semibold text-brand">{{ $data['bangsalName'] }}</p>
                            <p class="text-body dark:text-gray-300">{{ $data['roomName'] }}</p>
                        </div>

                        <div class="text-xs font-mono text-muted dark:text-gray-400">
                            SLS {{ $data['slsNo'] }}@if (!empty($data['riHdrNo'])) · RI {{ $data['riHdrNo'] }} @endif
                            <span class="block">{{ $data['slsDate'] }}</span>
                        </div>
                    </div>

                    {{-- Sub-kolom kanan: dokter pemberi resep + status --}}
                    <div class="space-y-2">
                        <div>
                            <span class="text-muted">Dokter:</span>
                            <p class="font-semibold text-body dark:text-gray-200">{{ $data['drName'] }}</p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @if (strtoupper($data['riStatus'] ?? '') === 'P')
                                <x-badge variant="gray">Sudah Pulang</x-badge>
                            @endif
                            @if (($data['status'] ?? 'A') === 'L')
                                <x-badge variant="success">Sudah Diproses Kasir</x-badge>
                            @else
                                <x-badge variant="warning">Belum Diproses Kasir</x-badge>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
