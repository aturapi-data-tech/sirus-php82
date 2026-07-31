<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

/**
 * Tambah transaksi Kamar Operasi baru.
 *
 * Padanan pembuatan record di form legacy `rit006x.fmb`: petugas memilih pasien
 * yang SEDANG dirawat (ri_status = 'I'), lalu header rstxn_oks dibuat dengan
 * status 'A' (Proses Transaksi) dan tarif masih kosong.
 */
new class extends Component {
    use WithRenderVersioningTrait;
    use EmrRITrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kamar-operasi-tambah-modal'];

    public string $searchPasien = '';
    public ?int $riHdrNo = null;
    public array $pasienTerpilih = [];

    public ?string $drId = null;
    public ?string $drIdOk = null;

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    private function isAllowedRole(): bool
    {
        $user = auth()->user();

        return $user ? $user->hasAnyRole(['Admin', 'Manager Umum', 'Supervisor Penunjang', 'Perawat']) : false;
    }

    #[On('kamar-operasi-tambah.open')]
    public function openTambah(): void
    {
        if (!$this->isAllowedRole()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses ke modul Kamar Operasi.');
            return;
        }

        $this->resetForm();
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
        $this->reset(['searchPasien', 'riHdrNo', 'pasienTerpilih', 'drId', 'drIdOk']);
    }

    /**
     * Kandidat pasien = kunjungan rawat inap yang masih aktif.
     * Transfer biaya hanya sah selama ri_status 'I', jadi tidak ada gunanya
     * membuat transaksi OK untuk pasien yang sudah pulang.
     */
    #[Computed]
    public function kandidatPasien()
    {
        $query = DB::table('rstxn_rihdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->select('h.rihdr_no', 'h.reg_no', 'p.reg_name', 'p.sex', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'p.address', 'r.room_name', DB::raw("to_char(h.entry_date,'dd/mm/yyyy hh24:mi') as entry_date"))
            ->where('h.ri_status', 'I')
            ->orderByDesc('h.rihdr_no');

        $keyword = trim($this->searchPasien);
        if ($keyword !== '' && mb_strlen($keyword) >= 2) {
            $upper = mb_strtoupper($keyword);
            $query->where(function ($subQuery) use ($keyword, $upper) {
                if (ctype_digit($keyword)) {
                    $subQuery->orWhere('h.rihdr_no', 'like', "%{$keyword}%");
                }
                $subQuery->orWhere(DB::raw('UPPER(p.reg_name)'), 'like', "%{$upper}%")->orWhere(DB::raw('UPPER(h.reg_no)'), 'like', "%{$upper}%");
            });
        }

        return $query->limit(25)->get();
    }

    public function pilihPasien(int $riHdrNo): void
    {
        $row = DB::table('rstxn_rihdrs as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->leftJoin('rsmst_rooms as r', 'r.room_id', '=', 'h.room_id')
            ->select('h.rihdr_no', 'h.reg_no', 'h.ri_status', 'h.dr_id', 'p.reg_name', 'p.sex', DB::raw("to_char(p.birth_date,'dd/mm/yyyy') as birth_date"), 'p.address', 'r.room_name')
            ->where('h.rihdr_no', $riHdrNo)
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan rawat inap tidak ditemukan.');
            return;
        }

        if (strtoupper((string) $row->ri_status) !== 'I') {
            $this->dispatch('toast', type: 'error', message: 'Pasien tersebut sudah tidak dirawat.');
            return;
        }

        $this->riHdrNo = (int) $row->rihdr_no;
        $this->pasienTerpilih = (array) $row;
        // DPJP rawat inap dipakai sebagai usulan operator; petugas boleh menggantinya.
        $this->drId = $row->dr_id ?: null;
    }

    public function batalPilihPasien(): void
    {
        $this->reset(['riHdrNo', 'pasienTerpilih', 'drId', 'drIdOk']);
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
        if (!$this->isAllowedRole()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return;
        }

        $validator = Validator::make(
            ['riHdrNo' => $this->riHdrNo, 'drId' => $this->drId, 'drIdOk' => $this->drIdOk],
            ['riHdrNo' => 'bail|required|integer', 'drId' => 'bail|required|string', 'drIdOk' => 'bail|required|string'],
            ['riHdrNo.required' => 'Pilih pasien rawat inap terlebih dahulu.', 'drId.required' => 'Dokter operator wajib dipilih.', 'drIdOk.required' => 'Dokter anestesi wajib dipilih.'],
        );

        if ($validator->fails()) {
            $this->dispatch('toast', type: 'error', message: $validator->errors()->first());
            return;
        }

        $riHdrNo = (int) $this->riHdrNo;
        $drId = $this->drId;
        $drIdOk = $this->drIdOk;
        $okRegBaru = null;

        // ok_reg = PK tanpa sequence → tabrakan ditangani dengan mengulang transaksi.
        for ($percobaan = 1; ; $percobaan++) {
            try {
                DB::transaction(function () use ($riHdrNo, $drId, $drIdOk, &$okRegBaru) {
                    $this->lockRIRow($riHdrNo);

                    $riStatus = DB::table('rstxn_rihdrs')->where('rihdr_no', $riHdrNo)->value('ri_status');
                    if (strtoupper((string) $riStatus) !== 'I') {
                        throw new \RuntimeException('Pasien sudah pulang — transaksi operasi tidak bisa dibuat.');
                    }

                    $okRegBaru = (int) DB::scalar('SELECT NVL(MAX(TO_NUMBER(ok_reg)),0) + 1 FROM rstxn_oks');

                    DB::table('rstxn_oks')->insert([
                        'ok_reg' => $okRegBaru,
                        'ok_date' => DB::raw('SYSDATE'),
                        'rihdr_no' => $riHdrNo,
                        'dr_id' => $drId,
                        'dr_id_ok' => $drIdOk,
                        'ok_status' => 'A',
                        // Semua 5.089 baris lama memakai '01'; dipertahankan supaya
                        // laporan lama yang memfilter kolom ini tetap melihat data baru.
                        'sl_codefrom' => '01',
                    ]);

                    $this->appendAdminLogRI($riHdrNo, "Buat transaksi OK No.{$okRegBaru} — operator {$drId}, anestesi {$drIdOk}", 'ADMIN');
                });

                break;
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', type: 'error', message: $e->getMessage());
                return;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($percobaan < 3 && str_contains($e->getMessage(), 'ORA-00001')) {
                    continue;
                }

                $this->dispatch('toast', type: 'error', message: 'Gagal membuat transaksi: ' . $e->getMessage());
                return;
            } catch (\Exception $e) {
                $this->dispatch('toast', type: 'error', message: 'Gagal membuat transaksi: ' . $e->getMessage());
                return;
            }
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
    <x-modal name="kamar-operasi-tambah" size="3xl" focusable>
        <div wire:key="{{ $this->renderKey('kamar-operasi-tambah-modal', [$riHdrNo ?: 'kosong']) }}">

            {{-- HEADER --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div>
                    <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Tambah Transaksi Kamar Operasi</h2>
                    <p class="text-xs text-muted">Pilih pasien yang sedang dirawat, lalu tentukan dokter operator dan anestesi</p>
                </div>
                <x-icon-button color="gray" type="button" wire:click="closeTambah">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </x-icon-button>
            </div>

            {{-- BODY --}}
            <div class="px-6 py-4 space-y-4">

                @if (empty($pasienTerpilih))
                    {{-- LANGKAH 1 — pilih pasien --}}
                    <div>
                        <x-input-label value="Cari Pasien Rawat Inap Aktif" />
                        <x-text-input type="text" wire:model.live.debounce.300ms="searchPasien" class="block w-full mt-1"
                            placeholder="Ketik No Inap / No RM / Nama pasien..." />
                        <p class="mt-1 text-xs text-muted">Hanya pasien berstatus Dirawat yang bisa dipilih.</p>
                    </div>

                    <div class="overflow-hidden border rounded-xl border-hairline dark:border-gray-700">
                        <div class="max-h-80 overflow-y-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="sticky top-0 text-xs font-semibold tracking-wide uppercase text-muted bg-surface-soft dark:bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2">No Inap</th>
                                        <th class="px-4 py-2">Pasien</th>
                                        <th class="px-4 py-2">Kamar</th>
                                        <th class="px-4 py-2 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                    @forelse ($this->kandidatPasien as $pasien)
                                        <tr wire:key="kandidat-{{ $pasien->rihdr_no }}" class="hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                            <td class="px-4 py-2 font-mono text-muted">{{ $pasien->rihdr_no }}</td>
                                            <td class="px-4 py-2">
                                                <x-list.identitas-pasien :regNo="$pasien->reg_no" :nama="$pasien->reg_name" :sex="$pasien->sex" :tglLahir="$pasien->birth_date" :alamat="$pasien->address" :collapseUmur="false" />
                                            </td>
                                            <td class="px-4 py-2 text-ink dark:text-gray-200">{{ $pasien->room_name ?? '-' }}</td>
                                            <td class="px-4 py-2 text-center">
                                                <x-primary-button type="button" wire:click="pilihPasien({{ $pasien->rihdr_no }})" class="text-xs">
                                                    Pilih
                                                </x-primary-button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-muted-soft">
                                                Tidak ada pasien rawat inap aktif yang cocok
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
                                    <span class="text-muted">No Inap:</span>
                                    <span class="ml-1 font-mono text-ink dark:text-gray-200">{{ $pasienTerpilih['rihdr_no'] ?? '-' }}</span>
                                    <span class="ml-3 text-muted">Kamar:</span>
                                    <span class="ml-1 text-ink dark:text-gray-200">{{ $pasienTerpilih['room_name'] ?? '-' }}</span>
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
                                :initialDrId="$drId" wire:key="tambah-lov-operator-{{ $riHdrNo }}-{{ $drId }}" />
                            <p class="mt-1 text-xs italic text-amber-700 dark:text-amber-400">Pendapatan pos JD Operator tercatat atas nama dokter ini.</p>
                        </div>
                        <div>
                            <livewire:lov.dokter.lov-dokter target="kamar-operasi-tambah-anestesi" label="Dokter Anestesi"
                                :initialDrId="$drIdOk" wire:key="tambah-lov-anestesi-{{ $riHdrNo }}-{{ $drIdOk }}" />
                            <p class="mt-1 text-xs italic text-amber-700 dark:text-amber-400">Pendapatan pos JD Anestesi tercatat atas nama dokter ini.</p>
                        </div>
                    </div>

                    <div class="p-3 text-xs border rounded-xl border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                        Transaksi dibuat dengan status <span class="font-semibold">Proses Transaksi</span> dan tarif masih kosong.
                        Setelah tersimpan, isi tindakan operasi lalu tekan Hitung Tarif OK.
                    </div>
                @endif

            </div>

            {{-- FOOTER --}}
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-hairline dark:border-gray-700">
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
