<?php
// resources/views/pages/transaksi/penunjang/kamar-operasi/omlop-kamar-operasi.blade.php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\Txn\Penunjang\KamarOperasiTrait;

/**
 * Tab Crew OM LOP (`rstxn_okomlops`).
 *
 * Baris di sini TIDAK ditagihkan ke pasien — yang masuk tagihan adalah pos
 * `omlop_fee` di header. Tabel ini merinci siapa saja petugasnya beserta jasanya
 * (jasa bertugas + jasa on call) untuk keperluan penggajian.
 *
 * Karena tidak menyentuh pos tarif, tambah/hapus di sini TIDAK memanggil
 * hitungUlang() — total tagihan pasien tidak boleh bergerak.
 */
new class extends Component {
    use KamarOperasiTrait;

    public string $okReg = '';
    public bool $isFormLocked = true;
    public int $riHdrNo = 0;

    public array $rows = [];

    public ?string $formEmpId = null;
    public string $formEmpName = '';

    public function mount(string $okReg = ''): void
    {
        $this->okReg = $okReg;
        $this->findData();
    }

    #[On('kamar-operasi.updated')]
    public function findData(): void
    {
        if ($this->okReg === '') {
            $this->rows = [];
            return;
        }

        $this->isFormLocked = $this->statusOk($this->okReg) !== 'A';
        $this->riHdrNo = $this->riHdrNoOk($this->okReg);

        $this->rows = DB::table('rstxn_okomlops as t')
            ->leftJoin('hrmst_employees as e', 'e.emp_id', '=', 't.emp_id')
            ->select('t.omlop_dtl', 't.emp_id', 'e.name as emp_name', 't.omlop_fee', 't.oncallomlop_fee')
            ->where('t.ok_reg', $this->okReg)
            ->orderBy('t.omlop_dtl')
            ->get()
            ->map(fn($crewOmlop) => (array) $crewOmlop)
            ->toArray();
    }

    private function bolehUbah(): bool
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return false;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah selesai/dibatalkan — data tidak bisa diubah.');
            $this->findData();
            return false;
        }

        return true;
    }

    #[On('lov.selected.kamar-operasi-omlop')]
    public function pilihPetugas($target = null, $payload = null): void
    {
        $this->formEmpId = $payload['emp_id'] ?? null;
        $this->formEmpName = $payload['name'] ?? '';

        // Tidak ada kolom angka sesudahnya — arahkan ke tombol Tambah supaya
        // Enter berikutnya langsung menyimpan.
        $this->dispatch('kamar-operasi-fokus', ke: 'ok-tombol-omlop');
    }

    public function tambah(): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        if (empty($this->formEmpId)) {
            $this->dispatch('toast', type: 'error', message: 'Pilih petugas terlebih dahulu.');
            return;
        }

        $empId = $this->formEmpId;
        $empName = $this->formEmpName;

        $sudahAda = collect($this->rows)->contains(fn($crewOmlop) => (string) ($crewOmlop['emp_id'] ?? '') === (string) $empId);
        if ($sudahAda) {
            $this->dispatch('toast', type: 'error', message: 'Petugas tersebut sudah terdaftar di transaksi ini.');
            return;
        }

        $riHdrNo = $this->riHdrNo;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($riHdrNo, $empId, $empName) {
            $this->kunciBarisOk($this->okReg);

            $nomor = (int) DB::scalar('SELECT NVL(MAX(omlop_dtl),0) + 1 FROM rstxn_okomlops');

            DB::table('rstxn_okomlops')->insert(['omlop_dtl' => $nomor, 'emp_id' => $empId, 'ok_reg' => $this->okReg]);

            $this->catatLogOk($riHdrNo, "Tambah crew OM LOP OK No.{$this->okReg} — {$empName} ({$empId})");
        }, 'Gagal menambah crew OM LOP');

        if (!$berhasil) {
            return;
        }

        $this->reset(['formEmpId', 'formEmpName']);
        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('toast', type: 'success', message: 'Crew OM LOP ditambahkan.');
        $this->dispatch('kamar-operasi-fokus', ke: 'ok-lov-omlop');
    }

    public function hapus(int $omlopDtl): void
    {
        if (!$this->bolehUbah()) {
            return;
        }

        $riHdrNo = $this->riHdrNo;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($riHdrNo, $omlopDtl) {
            $this->kunciBarisOk($this->okReg);

            $baris = DB::table('rstxn_okomlops')->where('omlop_dtl', $omlopDtl)->where('ok_reg', $this->okReg)->first();

            if (!$baris) {
                throw new \RuntimeException('Baris crew OM LOP tidak ditemukan.');
            }

            DB::table('rstxn_okomlops')->where('omlop_dtl', $omlopDtl)->where('ok_reg', $this->okReg)->delete();

            $this->catatLogOk($riHdrNo, "Hapus crew OM LOP OK No.{$this->okReg} — {$baris->emp_id}");
        }, 'Gagal menghapus crew OM LOP');

        if (!$berhasil) {
            return;
        }

        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('toast', type: 'success', message: 'Crew OM LOP dihapus.');
    }

    /**
     * Hook wire:model dari x-text-input-number. $key berbentuk "<indeks>.<kolom>".
     */
    public function updatedRows($value, $key): void
    {
        [$indeks, $kolom] = array_pad(explode('.', (string) $key, 2), 2, null);

        if ($kolom === null || !isset($this->rows[(int) $indeks]['omlop_dtl'])) {
            return;
        }

        $this->simpanJasa((int) $this->rows[(int) $indeks]['omlop_dtl'], $kolom, $value === null ? null : (string) $value);
    }

    /** Jasa per baris (jasa petugas, bukan tagihan pasien). */
    private function simpanJasa(int $omlopDtl, string $kolom, ?string $nilai): void
    {
        if (!in_array($kolom, ['omlop_fee', 'oncallomlop_fee'], true)) {
            return;
        }

        if (!$this->bolehUbah()) {
            return;
        }

        $bersih = str_replace(['.', ',', ' '], '', trim((string) $nilai));

        $validator = Validator::make(['jasa' => $bersih === '' ? null : $bersih], ['jasa' => 'bail|nullable|integer|min:0|max:999999999'], ['jasa.integer' => 'Jasa harus angka bulat.', 'jasa.min' => 'Jasa tidak boleh negatif.']);

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first('jasa'));
            $this->findData();
            return;
        }

        $nilaiBaru = $bersih === '' ? null : (int) $bersih;

        // Nilai lama HARUS dibaca dari DB, bukan dari $this->rows: baris itu ter-bind
        // wire:model sehingga sudah berisi nilai BARU saat hook dipanggil —
        // membandingkannya akan selalu "tidak berubah" dan simpan tak pernah jalan.
        $barisDb = DB::table('rstxn_okomlops')->where('omlop_dtl', $omlopDtl)->where('ok_reg', $this->okReg)->first();

        if (!$barisDb) {
            $this->dispatch('toast', type: 'error', message: 'Baris crew OM LOP tidak ditemukan.');
            $this->findData();
            return;
        }

        $nilaiLama = $barisDb->{$kolom} === null ? null : (int) $barisDb->{$kolom};

        if ($nilaiLama === $nilaiBaru) {
            return;
        }

        $riHdrNo = $this->riHdrNo;
        $label = $kolom === 'omlop_fee' ? 'Jasa OM LOP' : 'Jasa On Call OM LOP';
        $namaPetugas = collect($this->rows)->firstWhere('omlop_dtl', $omlopDtl)['emp_name'] ?? $barisDb->emp_id;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($omlopDtl, $kolom, $nilaiBaru, $nilaiLama, $riHdrNo, $label, $namaPetugas) {
            $this->kunciBarisOk($this->okReg);

            DB::table('rstxn_okomlops')->where('omlop_dtl', $omlopDtl)->where('ok_reg', $this->okReg)->update([$kolom => $nilaiBaru]);

            $teksLama = $nilaiLama === null ? '(belum diisi)' : 'Rp ' . number_format($nilaiLama);
            $teksBaru = $nilaiBaru === null ? '(belum diisi)' : 'Rp ' . number_format($nilaiBaru);
            $this->catatLogOk($riHdrNo, "Ubah {$label} OK No.{$this->okReg} — {$namaPetugas}: {$teksLama} → {$teksBaru}");
        }, 'Gagal menyimpan jasa OM LOP');

        if (!$berhasil) {
            $this->findData();
            return;
        }

        $this->findData();
        $this->dispatch('toast', type: 'success', message: "{$label} disimpan.");
    }
};
?>

<div>
    <p class="mb-3 text-xs text-muted dark:text-gray-400">
        <span class="font-semibold">Tidak ditagihkan ke pasien.</span>
        Daftar <span class="font-semibold">orang</span> yang bertugas beserta jasanya —
        yang masuk tagihan adalah pos <span class="font-semibold">OM LOP</span> di kartu tarif.
        Kolom <span class="font-semibold">Jasa Bertugas</span> = jasa saat bertugas;
        <span class="font-semibold">Jasa On Call</span> = tambahan karena dipanggil di luar jadwal.
    </p>

    @unless ($isFormLocked)
        <div class="grid grid-cols-1 gap-3 p-3 mb-4 border rounded-xl border-hairline dark:border-gray-700 bg-surface-soft dark:bg-gray-800/40 lg:grid-cols-12">
            {{-- Tab terakhir: Enter di kolom kosong = lanjut ke aksi simpan. --}}
            <div class="lg:col-span-10" id="ok-lov-omlop"
                x-on:keydown.enter="if (!$event.target.value?.trim()) $dispatch('kamar-operasi-fokus', { ke: 'ok-tombol-transfer' })">
                <livewire:lov.karyawan-oncall.lov-karyawan-oncall target="kamar-operasi-omlop" label="Petugas OM LOP"
                    :omlopOnly="true" :initialEmpId="$formEmpId" wire:key="lov-omlop-{{ $okReg }}-{{ $formEmpId }}" />
            </div>
            <div class="flex items-end lg:col-span-2">
                <x-primary-button id="ok-tombol-omlop" type="button" wire:click="tambah" wire:loading.attr="disabled"
                    wire:target="tambah" class="justify-center w-full text-xs">
                    <span wire:loading.remove wire:target="tambah">Tambah</span>
                    <span wire:loading wire:target="tambah" class="flex items-center gap-1">
                        <x-loading /> ...
                    </span>
                </x-primary-button>
            </div>
        </div>
    @endunless

    <div class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-sm font-semibold tracking-wide text-left text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                    <tr>
                        <th class="px-4 py-3">NIK</th>
                        <th class="px-4 py-3">Nama Petugas</th>
                        <th class="px-4 py-3 text-right" title="Jasa petugas saat bertugas — tidak ditagihkan ke pasien">Jasa Bertugas</th>
                        <th class="px-4 py-3 text-right" title="Tambahan jasa karena dipanggil di luar jadwal — tidak ditagihkan ke pasien">Jasa On Call</th>
                        @unless ($isFormLocked)
                            <th class="px-4 py-3 text-center">Aksi</th>
                        @endunless
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                    @forelse ($rows as $indeks => $row)
                        <tr wire:key="omlop-{{ $row['omlop_dtl'] }}" class="transition hover:bg-surface-soft dark:hover:bg-gray-800/40">
                            <td class="px-4 py-1.5 font-mono text-muted">{{ $row['emp_id'] ?? '-' }}</td>
                            <td class="px-4 py-1.5 text-ink dark:text-gray-200">{{ $row['emp_name'] ?? '-' }}</td>
                            @foreach (['omlop_fee', 'oncallomlop_fee'] as $kolomJasa)
                                <td class="px-4 py-1.5 text-right">
                                    @if ($isFormLocked)
                                        <span class="text-ink dark:text-gray-200 tabular-nums">
                                            {{ $row[$kolomJasa] === null ? '—' : 'Rp ' . number_format($row[$kolomJasa]) }}
                                        </span>
                                    @else
                                        {{-- Simpan dipicu hook updatedRows() saat blur. --}}
                                        <x-text-input-number wire:model="rows.{{ $indeks }}.{{ $kolomJasa }}" placeholder="0" />
                                    @endif
                                </td>
                            @endforeach
                            @unless ($isFormLocked)
                                <td class="px-4 py-1.5 text-center">
                                    <x-confirm-button variant="danger" action="hapus({{ $row['omlop_dtl'] }})"
                                        title="Hapus Crew OM LOP" message="Hapus petugas ini dari daftar crew OM LOP?"
                                        confirmText="Ya, hapus" cancelText="Batal" class="!px-2 !py-1 text-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </x-confirm-button>
                                </td>
                            @endunless
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-muted-soft">Belum ada crew OM LOP</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
