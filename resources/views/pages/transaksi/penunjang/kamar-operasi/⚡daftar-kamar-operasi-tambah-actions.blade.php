<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Penunjang\KamarOperasiTrait;

/**
 * Tambah transaksi Kamar Operasi baru.
 *
 * Padanan pembuatan record di form legacy `rit006x.fmb`, diperluas ke tiga
 * layanan seperti modul Laboratorium: petugas memilih SUMBER (RJ / UGD / RI)
 * lalu pasien yang kunjungannya masih aktif, dan header rstxn_oks dibuat
 * dengan status 'A' (Proses Transaksi) serta tarif masih kosong.
 *
 *   RJ / UGD → rj_status = 'A' & rj_date hari ini  (pola daftar-laborat-tambah)
 *   RI       → ri_status = 'I' (tanpa filter tanggal; pasien bisa masuk kemarin)
 *
 * Kunjungan induk disimpan di `status_rjri` + `ref_no`; `rihdr_no` hanya diisi
 * untuk RI demi laporan lama yang masih membacanya.
 */
new class extends Component {
    use WithRenderVersioningTrait;
    use KamarOperasiTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kamar-operasi-tambah-modal'];

    public string $sumber = 'RI';
    public string $searchPasien = '';
    public ?int $refNo = null;
    public array $pasienTerpilih = [];

    public ?string $drId = null;
    public ?string $drIdOk = null;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    #[On('kamar-operasi-tambah.open')]
    public function openTambah(string $sumber = 'RI'): void
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses ke modul Kamar Operasi.');
            return;
        }

        $this->resetForm();
        $this->sumber = in_array($sumber, self::SUMBER_OK, true) ? $sumber : 'RI';
        $this->incrementVersion('kamar-operasi-tambah-modal');
        $this->dispatch('open-modal', name: 'kamar-operasi-tambah');
    }

    public function closeTambah(): void
    {
        $this->dispatch('close-modal', name: 'kamar-operasi-tambah');
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['searchPasien', 'refNo', 'pasienTerpilih', 'drId', 'drIdOk']);
        unset($this->kandidatPasien);
    }

    /** Ganti sumber layanan — pasien & dokter usulan ikut tereset. */
    public function gantiSumber(string $sumber): void
    {
        if (!in_array($sumber, self::SUMBER_OK, true) || $sumber === $this->sumber) {
            return;
        }

        $this->sumber = $sumber;
        $this->resetForm();
        $this->incrementVersion('kamar-operasi-tambah-modal');
    }

    /** Label sumber untuk view — helper traitnya protected, view tak bisa memanggilnya. */
    #[Computed]
    public function labelSumber(): string
    {
        return $this->labelSumberOk($this->sumber);
    }

    /**
     * Kandidat pasien = kunjungan yang masih aktif pada sumber terpilih.
     * Transfer biaya hanya sah selama kunjungannya aktif, jadi tidak ada
     * gunanya membuat transaksi OK untuk kunjungan yang sudah ditutup.
     */
    #[Computed]
    public function kandidatPasien()
    {
        $kolomPasien = ['h.reg_no', 'p.reg_name', 'p.sex', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'p.address'];

        if ($this->sumber === 'RI') {
            $query = DB::table('rstxn_rihdrs as h')
                ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
                ->select(array_merge($kolomPasien, ['h.rihdr_no as ref_no', 'h.dr_id', 'r.room_name as unit_name', DB::raw("to_char(h.entry_date,'dd/mm/yyyy hh24:mi') as masuk_date")]))
                ->whereRaw("NVL(h.ri_status,'I') = 'I'")
                ->orderByDesc('h.rihdr_no');
        } else {
            // RJ & UGD sama-sama rstxn_*hdrs ber-rj_no; yang beda hanya unit asal.
            $tabel = $this->sumber === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
            $query = DB::table($tabel . ' as h')
                ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
                ->select(array_merge($kolomPasien, ['h.rj_no as ref_no', 'h.dr_id', DB::raw("to_char(h.rj_date,'dd/mm/yyyy hh24:mi') as masuk_date")]))
                ->whereRaw("NVL(h.rj_status,'A') = 'A'")
                ->whereRaw('TRUNC(h.rj_date) = TRUNC(SYSDATE)')
                ->orderByDesc('h.rj_no');

            if ($this->sumber === 'UGD') {
                $query->leftJoin('rsmst_entrytypes as e', 'e.entry_id', '=', 'h.entry_id')->addSelect('e.entry_desc as unit_name');
            } else {
                $query->leftJoin('rsmst_polis as po', 'po.poli_id', '=', 'h.poli_id')->addSelect('po.poli_desc as unit_name');
            }
        }

        $keyword = trim($this->searchPasien);
        if ($keyword !== '' && mb_strlen($keyword) >= 2) {
            $upper = mb_strtoupper($keyword);
            $kolomNomor = $this->sumber === 'RI' ? 'h.rihdr_no' : 'h.rj_no';
            $query->where(function ($subQuery) use ($keyword, $upper, $kolomNomor) {
                if (ctype_digit($keyword)) {
                    $subQuery->orWhere($kolomNomor, 'like', "%{$keyword}%");
                }
                $subQuery->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$upper}%")->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$upper}%");
            });
        }

        return $query->limit(25)->get();
    }

    public function pilihPasien(int $refNo): void
    {
        $baris = $this->kandidatPasien->firstWhere('ref_no', $refNo);

        if (!$baris) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan ' . $this->labelSumberOk($this->sumber) . ' tidak ditemukan / sudah tidak aktif.');
            return;
        }

        $this->refNo = $refNo;
        $this->pasienTerpilih = (array) $baris;
        // Dokter kunjungan (DPJP untuk RI) dipakai sebagai usulan operator;
        // petugas boleh menggantinya.
        $this->drId = $baris->dr_id ?: null;
    }

    public function batalPilihPasien(): void
    {
        $this->reset(['refNo', 'pasienTerpilih', 'drId', 'drIdOk']);
    }

    #[On('lov.selected.kamar-operasi-tambah-operator')]
    public function pilihOperator($target = null, $payload = null): void
    {
        $this->drId = $payload['dr_id'] ?? null;
    }

    #[On('lov.selected.kamar-operasi-tambah-anestesi')]
    public function pilihAnestesi($target = null, $payload = null): void
    {
        $this->drIdOk = $payload['dr_id'] ?? null;
    }

    /**
     * Buat header transaksi. dr_id & dr_id_ok NOT NULL di rstxn_oks, jadi keduanya
     * wajib dipilih di depan — bukan diisi belakangan.
     */
    public function simpanOperasi(): void
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return;
        }

        $validator = Validator::make(
            ['refNo' => $this->refNo, 'drId' => $this->drId, 'drIdOk' => $this->drIdOk],
            ['refNo' => 'bail|required|integer', 'drId' => 'bail|required|string', 'drIdOk' => 'bail|required|string'],
            ['refNo.required' => 'Pilih pasien terlebih dahulu.', 'drId.required' => 'Dokter operator wajib dipilih.', 'drIdOk.required' => 'Dokter anestesi wajib dipilih.'],
        );

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        if (!in_array($this->sumber, self::SUMBER_OK, true)) {
            $this->dispatch('toast', type: 'error', message: 'Sumber layanan tidak dikenali.');
            return;
        }

        $sumber = $this->sumber;
        $refNo = (int) $this->refNo;
        $drId = $this->drId;
        $drIdOk = $this->drIdOk;
        $okRegBaru = null;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($sumber, $refNo, $drId, $drIdOk, &$okRegBaru) {
            $statusInduk = $this->kunciIndukOk($sumber, $refNo);

            if (!$this->indukAktifOk($sumber, $statusInduk)) {
                throw new \RuntimeException($this->sebabIndukTerkunciOk($sumber, $statusInduk) . ' — transaksi operasi tidak bisa dibuat.');
            }

            $okRegBaru = (int) DB::scalar('SELECT NVL(MAX(TO_NUMBER(ok_reg)),0) + 1 FROM rstxn_oks');

            DB::table('rstxn_oks')->insert([
                'ok_reg' => $okRegBaru,
                'ok_date' => DB::raw('SYSDATE'),
                // status_rjri + ref_no = sumber kebenaran layanan (pola lab).
                // rihdr_no HANYA untuk RI — kolomnya FK ke rstxn_rihdrs, jadi
                // rj_no RJ/UGD tidak boleh dititipkan ke sana.
                'status_rjri' => $sumber,
                'ref_no' => $refNo,
                'rihdr_no' => $sumber === 'RI' ? $refNo : null,
                'dr_id' => $drId,
                'dr_id_ok' => $drIdOk,
                'ok_status' => 'A',
                // Semua 5.089 baris lama memakai '01'; dipertahankan supaya
                // laporan lama yang memfilter kolom ini tetap melihat data baru.
                'sl_codefrom' => '01',
            ]);

            $this->catatLogOk($sumber, $refNo, "Buat transaksi OK No.{$okRegBaru} — operator {$drId}, anestesi {$drIdOk}");
        }, 'Gagal membuat transaksi');

        if (!$berhasil) {
            return;
        }

        $this->closeTambah();
        $this->dispatch('refresh-after-kamar-operasi.saved');
        $this->dispatch('toast', type: 'success', message: "Transaksi operasi No.{$okRegBaru} dibuat. Lanjutkan dengan mengisi tindakan.");
        // Langsung buka modal transaksinya supaya petugas bisa lanjut mengisi tindakan.
        $this->dispatch('kamar-operasi-actions.open', okReg: (string) $okRegBaru);
    }
};
?>

<div>
    <x-modal name="kamar-operasi-tambah" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('kamar-operasi-tambah-modal', [$sumber, $refNo ?: 'kosong']) }}">

            {{-- HEADER --}}
            <div class="flex items-start justify-between px-6 py-4 border-b shrink-0 border-hairline dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Tambah Transaksi Kamar Operasi</h2>
                    <p class="text-xs text-muted">Pilih layanan asal pasien, lalu tentukan dokter operator dan anestesi</p>

                    {{-- Pilihan sumber layanan — pola sama dengan Tambah Pemeriksaan Laboratorium. --}}
                    <div class="inline-flex mt-3 overflow-hidden border rounded-lg border-hairline dark:border-gray-700">
                        @foreach (['RJ' => 'Rawat Jalan', 'UGD' => 'UGD', 'RI' => 'Rawat Inap'] as $kunci => $label)
                            <button type="button" wire:click="gantiSumber('{{ $kunci }}')"
                                @class([
                                    'px-3 py-1.5 text-sm font-medium transition',
                                    'bg-brand-green text-white' => $sumber === $kunci,
                                    'bg-canvas text-body hover:bg-surface-soft dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800' => $sumber !== $kunci,
                                ])>
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>
                <x-icon-button color="gray" type="button" wire:click="closeTambah">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- BODY — area scroll; daftar pasien bisa panjang, jadi hanya bagian
                 ini yang bergulir sementara header & footer tetap di tempat. --}}
            <div class="flex-1 min-h-0 px-6 py-5 overflow-y-auto bg-surface-soft dark:bg-gray-950/20">
                <div class="w-full space-y-5">

                @php
                    // Istilah kolom ikut sumbernya — "No Inap"/"Kamar" salah untuk RJ & UGD.
                    $labelNomor = $sumber === 'RI' ? 'No Inap' : 'No Reg';
                    $labelUnit = match ($sumber) {
                        'RI' => 'Kamar',
                        'UGD' => 'Cara Masuk',
                        default => 'Poli',
                    };
                    $labelCari = match ($sumber) {
                        'RI' => 'Cari Pasien Rawat Inap Aktif',
                        'UGD' => 'Cari Pasien UGD Hari Ini',
                        default => 'Cari Pasien Rawat Jalan Hari Ini',
                    };
                    $catatanCari = $sumber === 'RI' ? 'Hanya pasien berstatus Dirawat yang bisa dipilih.' : 'Hanya kunjungan hari ini yang belum dibayar di kasir yang bisa dipilih.';
                @endphp

                @if (empty($pasienTerpilih))
                    {{-- LANGKAH 1 — pilih pasien --}}
                    <div>
                        <x-input-label :value="$labelCari" />
                        <x-text-input type="text" wire:model.live.debounce.300ms="searchPasien" class="block w-full mt-1"
                            placeholder="Ketik {{ $labelNomor }} / No RM / Nama pasien..." />
                        <p class="mt-1 text-xs text-muted">{{ $catatanCari }}</p>
                    </div>

                    <div class="overflow-hidden border rounded-xl border-hairline dark:border-gray-700">
                        <div class="max-h-80 overflow-y-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="sticky top-0 text-xs font-semibold tracking-wide uppercase text-muted bg-surface-soft dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2">{{ $labelNomor }}</th>
                                        <th class="px-4 py-2">Pasien</th>
                                        <th class="px-4 py-2">{{ $labelUnit }}</th>
                                        <th class="px-4 py-2 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                    @forelse ($this->kandidatPasien as $pasien)
                                        <tr wire:key="kandidat-{{ $sumber }}-{{ $pasien->ref_no }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                            <td class="px-4 py-2 font-mono text-muted">{{ $pasien->ref_no }}</td>
                                            <td class="px-4 py-2">
                                                <x-list.identitas-pasien :regNo="$pasien->reg_no" :nama="$pasien->reg_name" :sex="$pasien->sex" :tglLahir="$pasien->birth_date" :alamat="$pasien->address" :collapseUmur="false" />
                                            </td>
                                            <td class="px-4 py-2 text-ink dark:text-gray-200">{{ $pasien->unit_name ?? '-' }}</td>
                                            <td class="px-4 py-2 text-center">
                                                <x-primary-button type="button" wire:click="pilihPasien({{ $pasien->ref_no }})" class="text-xs">
                                                    Pilih
                                                </x-primary-button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-muted-soft">
                                                Tidak ada pasien {{ $sumber === 'RI' ? 'rawat inap aktif' : 'aktif hari ini' }} yang cocok
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @else
                    {{-- LANGKAH 2 — dokter --}}
                    <div class="p-4 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <x-list.identitas-pasien :regNo="$pasienTerpilih['reg_no'] ?? null" :nama="$pasienTerpilih['reg_name'] ?? null" :sex="$pasienTerpilih['sex'] ?? null" :tglLahir="$pasienTerpilih['birth_date'] ?? null" :alamat="$pasienTerpilih['address'] ?? null" :collapseUmur="false" />
                                <div class="mt-2 text-sm">
                                    <span class="text-muted">{{ $labelNomor }}:</span>
                                    <span class="ml-1 font-mono text-ink dark:text-gray-200">{{ $pasienTerpilih['ref_no'] ?? '-' }}</span>
                                    <span class="ml-3 text-muted">{{ $labelUnit }}:</span>
                                    <span class="ml-1 text-ink dark:text-gray-200">{{ $pasienTerpilih['unit_name'] ?? '-' }}</span>
                                    <x-badge variant="gray" class="ml-3">{{ $this->labelSumber }}</x-badge>
                                </div>
                            </div>
                            <x-secondary-button type="button" wire:click="batalPilihPasien" class="text-xs whitespace-nowrap">
                                Ganti Pasien
                            </x-secondary-button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <livewire:lov.dokter.lov-dokter target="kamar-operasi-tambah-operator" label="Dokter Operator"
                                :initialDrId="$drId" wire:key="tambah-lov-operator-{{ $sumber }}-{{ $refNo }}-{{ $drId }}" />
                            <p class="mt-1 text-xs italic text-amber-700 dark:text-amber-400">Pendapatan pos Jasa Dokter Operator tercatat atas nama dokter ini.</p>
                        </div>
                        <div>
                            <livewire:lov.dokter.lov-dokter target="kamar-operasi-tambah-anestesi" label="Dokter Anestesi"
                                :initialDrId="$drIdOk" wire:key="tambah-lov-anestesi-{{ $sumber }}-{{ $refNo }}-{{ $drIdOk }}" />
                            <p class="mt-1 text-xs italic text-amber-700 dark:text-amber-400">Pendapatan pos Jasa Dokter Anestesi tercatat atas nama dokter ini.</p>
                        </div>
                    </div>

                    <div class="p-3 text-xs border rounded-xl border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                        Transaksi dibuat dengan status <span class="font-semibold">Proses Transaksi</span> dan tarif masih kosong.
                        Setelah tersimpan, isi tindakan operasi lalu tekan Hitung Tarif OK.
                    </div>
                @endif

                </div>
            </div>

            {{-- FOOTER --}}
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t shrink-0 border-hairline dark:border-gray-700">
                <x-secondary-button type="button" wire:click="closeTambah">Tutup</x-secondary-button>

                @if (!empty($pasienTerpilih))
                    <x-primary-button type="button" wire:click="simpanOperasi" wire:loading.attr="disabled" wire:target="simpanOperasi">
                        <span wire:loading.remove wire:target="simpanOperasi">Simpan &amp; Lanjut Isi Tindakan</span>
                        <span wire:loading wire:target="simpanOperasi" class="flex items-center gap-1.5">
                            <x-loading /> Menyimpan...
                        </span>
                    </x-primary-button>
                @endif
            </div>

        </div>
    </x-modal>
</div>
