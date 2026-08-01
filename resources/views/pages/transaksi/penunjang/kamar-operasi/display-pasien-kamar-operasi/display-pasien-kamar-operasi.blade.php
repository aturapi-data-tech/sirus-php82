<?php

use Livewire\Component;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Master\MasterPasien\MasterPasienTrait;
use App\Http\Traits\Txn\Penunjang\KamarOperasiTrait;

/**
 * Display pasien + info transaksi Kamar Operasi.
 *
 * Struktur & tema disamakan dengan display-pasien RJ/UGD/RI dan
 * display-pasien-laborat: satu kartu, kiri identitas pasien (lengkap dengan
 * NIK/BPJS/telepon/RT-RW via MasterPasienTrait), kanan info transaksi.
 */
new class extends Component {
    use MasterPasienTrait, KamarOperasiTrait;

    #[Reactive]
    public ?string $okReg = null;

    public array $headerData = [];
    public array $pasienData = [];

    public function mount(): void
    {
        $this->loadData();
    }

    public function rendering(): void
    {
        $this->loadData();
    }

    /**
     * okReg tidak berubah selama modal terbuka, jadi #[Reactive] saja tidak cukup:
     * perubahan crew/tarif/status di komponen induk tidak akan terlihat di sini
     * tanpa dipicu event.
     */
    #[On('refresh-after-kamar-operasi.saved')]
    public function segarkan(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        if (empty($this->okReg)) {
            $this->headerData = [];
            $this->pasienData = [];
            return;
        }

        // Sengaja RINGKAS: dokter, tindakan, dan diagnosa pra-op TIDAK diambil di
        // sini karena sudah ditampilkan di kartu "Crew & Tindakan Operasi" — kalau
        // ikut ditarik, informasinya dobel dan kolom kanan memanjang ke bawah.
        ['sumber' => $sumber, 'refNo' => $refNo] = $this->sumberRefOk($this->okReg);

        // Kunjungan induk di-join sesuai layanan asalnya; nama kolom hasil
        // diseragamkan (status_induk / unit_name) supaya template tidak perlu
        // tahu apakah sumbernya ri_status atau rj_status.
        $query = DB::table('rstxn_oks as o')
            ->select('o.ok_reg', 'o.ok_status', DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi:ss') as ok_date"), 'h.reg_no', DB::raw((string) $refNo . ' as induk_no'))
            ->where('o.ok_reg', $this->okReg);

        if ($sumber === 'RI') {
            $query->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'o.ref_no')
                ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
                ->addSelect(DB::raw('h.ri_status as status_induk'), DB::raw('r.room_name as unit_name'));
        } elseif ($sumber === 'UGD') {
            $query->join('rstxn_ugdhdrs as h', 'h.rj_no', '=', 'o.ref_no')
                ->leftJoin('rsmst_entrytypes as e', 'e.entry_id', '=', 'h.entry_id')
                ->addSelect(DB::raw('h.rj_status as status_induk'), DB::raw('e.entry_desc as unit_name'));
        } else {
            $query->join('rstxn_rjhdrs as h', 'h.rj_no', '=', 'o.ref_no')
                ->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')
                ->addSelect(DB::raw('h.rj_status as status_induk'), DB::raw('po.poli_desc as unit_name'));
        }

        $header = $query->first();

        if ($header) {
            $header->sumber = $sumber;
        }

        if (!$header) {
            $this->headerData = [];
            $this->pasienData = [];
            return;
        }

        $this->headerData = (array) $header;
        $this->pasienData = $this->findDataMasterPasien($header->reg_no) ?? [];
    }
};
?>

<div>
    @if (!empty($pasienData) && !empty($headerData))
        @php
            $pasien = $pasienData['pasien'] ?? [];
            $header = $headerData;

            $statusKode = $header['ok_status'] ?? 'A';
            $statusKode = $statusKode !== '' ? $statusKode : 'A';
            [$statusText, $statusClass] = match ($statusKode) {
                'A' => ['Proses Transaksi', 'bg-amber-100 text-amber-700 border-amber-200'],
                'L' => ['Transaksi Selesai', 'bg-green-100 text-green-700 border-green-200'],
                'F' => ['Dibatalkan', 'bg-error/10 text-error border-error/30'],
                default => [$statusKode, 'bg-surface-soft text-muted border-hairline'],
            };

            $sumberOk = strtoupper($header['sumber'] ?? 'RI');
            [$sumberLabel, $sumberClass, $labelNomorInduk, $labelUnit] = match ($sumberOk) {
                'RJ' => ['Rawat Jalan', 'bg-sky-100 text-sky-700 border-sky-200', 'No Reg', 'Poli'],
                'UGD' => ['Gawat Darurat', 'bg-rose-100 text-rose-700 border-rose-200', 'No Reg', 'Cara Masuk'],
                default => ['Rawat Inap', 'bg-purple-100 text-purple-700 border-purple-200', 'No Inap', 'Kamar'],
            };

            // Status kunjungan induk — RI memakai ri_status, RJ/UGD rj_status;
            // query sudah menyeragamkannya jadi status_induk.
            $statusIndukKode = strtoupper($header['status_induk'] ?? '');
            $indukNetral = 'bg-surface-soft text-muted border-hairline';
            [$indukLabel, $indukClass] = $sumberOk === 'RI'
                ? match ($statusIndukKode) {
                    'I' => ['Dirawat', 'bg-brand/10 text-brand border-brand/30'],
                    'P' => ['Pulang', 'bg-amber-100 text-amber-700 border-amber-200'],
                    'L' => ['Pulang', $indukNetral],
                    'F' => ['Batal', 'bg-error/10 text-error border-error/30'],
                    default => [$statusIndukKode ?: '-', $indukNetral],
                }
                : match ($statusIndukKode) {
                    'A' => ['Aktif', 'bg-brand/10 text-brand border-brand/30'],
                    'L' => ['Sudah Dibayar', 'bg-amber-100 text-amber-700 border-amber-200'],
                    'I' => ['Dirawat Inap', $indukNetral],
                    'F' => ['Batal', 'bg-error/10 text-error border-error/30'],
                    default => [$statusIndukKode ?: '-', $indukNetral],
                };

            $alamat = trim($pasien['identitas']['alamat'] ?? '');
            $rt = trim($pasien['identitas']['rt'] ?? '');
            $rw = trim($pasien['identitas']['rw'] ?? '');
            $alamatLine = $alamat;
            if ($rt !== '' || $rw !== '') {
                $alamatLine .= " RT {$rt}/RW {$rw}";
            }

            $tglLahirRaw = $pasien['tglLahir'] ?? '';
            $tglLahirFmt = '-';
            if (!empty($tglLahirRaw)) {
                try {
                    $tglLahirFmt = \Carbon\Carbon::parse($tglLahirRaw)->format('d/m/Y');
                } catch (\Exception) {
                    $tglLahirFmt = $tglLahirRaw;
                }
            }
            $tempatLahir = trim($pasien['tempatLahir'] ?? '');
            $tglLahirLabel = $tempatLahir !== '' ? "{$tempatLahir}, {$tglLahirFmt}" : $tglLahirFmt;
        @endphp

        <div class="px-4 py-3 text-base leading-relaxed border border-hairline rounded-lg bg-canvas dark:bg-gray-900 dark:border-gray-700">
            <div class="grid grid-cols-1 gap-x-6 gap-y-3 lg:grid-cols-5">

                {{-- ===== KIRI: Identifikasi Pasien ===== --}}
                <div class="space-y-2 lg:col-span-3 lg:border-r lg:border-hairline dark:lg:border-gray-700 lg:pr-4">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-xl font-bold text-ink dark:text-white">
                            {{ $pasien['regName'] ?? '-' }}
                        </span>
                        <span class="font-mono text-base text-muted dark:text-gray-400 shrink-0">
                            {{ $pasien['regNo'] ?? '-' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 gap-x-4 gap-y-1.5 sm:grid-cols-2">
                        {{-- Demografi --}}
                        <div class="space-y-1">
                            <div>
                                <span class="text-muted">Jenis Kelamin:</span>
                                <span class="ml-1 text-body dark:text-gray-300">
                                    {{ $pasien['jenisKelamin']['jenisKelaminDesc'] ?? '-' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-muted">Umur:</span>
                                <span class="ml-1 text-body dark:text-gray-300">
                                    {{ $pasien['thn'] ?? 0 }} Thn {{ $pasien['bln'] ?? 0 }} Bln {{ $pasien['hari'] ?? 0 }} Hr
                                </span>
                            </div>
                            <div>
                                <span class="text-muted">Tgl Lahir:</span>
                                <span class="ml-1 text-body dark:text-gray-300">{{ $tglLahirLabel }}</span>
                            </div>
                        </div>

                        {{-- Kontak & Identitas --}}
                        <div class="space-y-1">
                            @if ($alamatLine !== '')
                                <div class="text-body dark:text-gray-300">📍 {{ $alamatLine }}</div>
                            @endif

                            @if (!empty($pasien['kontak']['nomerTelponSelulerPasien']))
                                <div class="text-body dark:text-gray-300">📞 {{ $pasien['kontak']['nomerTelponSelulerPasien'] }}</div>
                            @endif

                            <div class="font-mono text-xs text-muted dark:text-gray-400">
                                🆔
                                NIK: {{ $pasien['identitas']['nik'] ?? '-' }}
                                @if (!empty($pasien['identitas']['idbpjs']))
                                    &bull; BPJS: {{ $pasien['identitas']['idbpjs'] }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== KANAN: Info Transaksi Operasi ===== --}}
                <div class="space-y-2 lg:col-span-2">
                    {{-- Badge + No Txn --}}
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $sumberClass }}">
                                {{ $sumberLabel }}
                            </span>
                            <span class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $indukClass }}">
                                {{ $indukLabel }}
                            </span>
                            <span class="inline-block border rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-muted">No Txn:</span>
                            <span class="ml-1 font-mono font-bold text-brand">{{ $header['ok_reg'] }}</span>
                        </div>
                    </div>

                    <div>
                        <span class="text-muted">Tanggal:</span>
                        <span class="ml-1 text-body dark:text-gray-300">{{ $header['ok_date'] ?? '-' }}</span>
                    </div>

                    <div>
                        <span class="text-muted">{{ $labelNomorInduk }}:</span>
                        <span class="ml-1 font-mono text-body dark:text-gray-300">{{ $header['induk_no'] }}</span>
                        @if (!empty($header['unit_name']))
                            <span class="ml-2 text-muted">{{ $labelUnit }}:</span>
                            <span class="ml-1 text-body dark:text-gray-300">{{ $header['unit_name'] }}</span>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-12 text-gray-300 dark:text-gray-600">
            <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <p class="text-sm font-medium">Data pasien belum dimuat</p>
        </div>
    @endif
</div>
