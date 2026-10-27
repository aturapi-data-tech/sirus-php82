<?php

/**
 * pages/database-monitor/user-control/kas-user-control/kas-user-control-actions.blade.php
 */

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?int $userId = null;
    public string $userName = '';
    public string $userEmail = '';
    public ?string $empId = null;
    public ?string $empName = null;

    public array $availableKas = [];
    public array $userKasIds = [];
    public string $searchKas = '';

    #[On('kasUserControl.openManage')]
    public function openManage(int $userId): void
    {
        $user = DB::table('users')->select('id', 'name', 'email', 'emp_id')->where('id', $userId)->first();

        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User tidak ditemukan.');
            return;
        }

        $this->userId = $user->id;
        $this->userName = $user->name ?? '';
        $this->userEmail = $user->email ?? '';
        $this->empId = $user->emp_id ? (string) $user->emp_id : null;
        $this->empName = null;
        $this->searchKas = '';

        if ($this->empId) {
            $emp = DB::table('immst_employers')->select('emp_name')->where('emp_id', $this->empId)->first();
            $this->empName = $emp?->emp_name ?? null;
        }

        $this->loadUserKas();
        $this->loadAvailableKas();

        $this->dispatch('open-modal', name: 'kas-user-control-actions');
    }

    public function updatedSearchKas(): void
    {
        $this->loadAvailableKas();
    }

    protected function loadUserKas(): void
    {
        $this->userKasIds = DB::table('user_kas')->where('user_id', $this->userId)->pluck('acc_id')->map(fn($v) => (string) $v)->all();
    }

    protected function loadAvailableKas(): void
    {
        $keyword = trim($this->searchKas);
        $upperKeyword = mb_strtoupper($keyword);

        $rows = DB::table('acmst_accounts as a')
            ->join('acmst_kases as b', 'a.acc_id', '=', 'b.acc_id')
            ->select('a.acc_id', 'a.acc_name')
            ->when($keyword !== '', fn($q) => $q->where(fn($q2) => $q2->where('a.acc_id', 'like', "%{$keyword}%")->orWhereRaw('UPPER(a.acc_name) LIKE ?', ["%{$upperKeyword}%"])))
            ->orderBy('a.acc_id')
            ->get();

        $this->availableKas = $rows
            ->map(
                fn($row) => [
                    'acc_id' => (string) $row->acc_id,
                    'acc_name' => (string) ($row->acc_name ?? ''),
                    'assigned' => in_array((string) $row->acc_id, $this->userKasIds),
                ],
            )
            ->toArray();
    }

    public function toggleKas(string $accId): void
    {
        if (!$this->userId) {
            return;
        }

        $exists = DB::table('user_kas')->where('user_id', $this->userId)->where('acc_id', $accId)->exists();

        if ($exists) {
            DB::table('user_kas')->where('user_id', $this->userId)->where('acc_id', $accId)->delete();

            $this->dispatch('toast', type: 'info', message: "Kas {$accId} dicabut.");
        } else {
            // Tanpa EMP ID transaksi kas tak punya nama petugas — kas baru tak boleh diberikan.
            // Mencabut kas yang sudah terlanjur ada tetap boleh.
            if (!$this->empId) {
                $this->dispatch('toast', type: 'error', message: 'Isi EMP ID user ini dulu (Edit User) sebelum memberi akun kas.');
                return;
            }

            DB::table('user_kas')->insert([
                'id_user_kas' => DB::raw('(SELECT NVL(MAX(id_user_kas), 0) + 1 FROM user_kas)'),
                'user_id' => $this->userId,
                'acc_id' => $accId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->dispatch('toast', type: 'success', message: "Kas {$accId} diberikan.");
        }

        $this->loadUserKas();
        $this->loadAvailableKas();
        $this->dispatch('refresh-after-user-control.saved');
    }

    public function revokeAll(): void
    {
        if (!$this->userId) {
            return;
        }

        DB::table('user_kas')->where('user_id', $this->userId)->delete();

        $this->userKasIds = [];
        $this->loadAvailableKas();

        $this->dispatch('toast', type: 'warning', message: "Semua kas {$this->userName} dicabut.");
        $this->dispatch('refresh-after-user-control.saved');
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'kas-user-control-actions');
    }
};
?>

<div>
    {{-- ✅ Standar: size="full" height="full" --}}
    <x-modal name="kas-user-control-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]">

            {{-- ── HEADER ── --}}
            <x-modul-dokumen.header judul="Kelola Akun Kas"
                ikon="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                {{ $userName }}
                @if ($userEmail)
                    <span class="text-muted-soft">· {{ $userEmail }}</span>
                @endif
                <x-slot:badge>
                    <x-badge variant="brand">{{ count($userKasIds) }} kas aktif</x-badge>
                    @if ($empId)
                        <x-badge variant="success">EMP: {{ $empId }}</x-badge>
                    @else
                        <x-badge variant="danger">EMP ID belum diset</x-badge>
                    @endif
                </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- ── BODY ── --}}
            <div class="flex-1 px-6 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- Info EMP ID (read-only) --}}
                    @if ($empId)
                        <div
                            class="flex items-center gap-3 p-3 border border-emerald-200 rounded-xl bg-emerald-50 dark:border-emerald-800/40 dark:bg-emerald-900/10">
                            <div
                                class="flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 shrink-0">
                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Karyawan
                                    terhubung</p>
                                <p class="text-sm font-bold text-emerald-800 truncate dark:text-emerald-200">
                                    {{ $empName ?? '(nama tidak ditemukan)' }}
                                </p>
                                <p class="text-xs font-mono text-emerald-600 dark:text-emerald-400">{{ $empId }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-[10px] text-emerald-500">Ubah via</p>
                                <p class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Edit User
                                </p>
                            </div>
                        </div>
                    @else
                        <div
                            class="flex items-start gap-3 p-4 border border-amber-200 rounded-xl bg-amber-50 dark:border-amber-800/40 dark:bg-amber-900/10">
                            <svg class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <div>
                                <p class="text-sm font-semibold text-amber-700 dark:text-amber-300">EMP ID belum diisi
                                </p>
                                <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">
                                    EMP ID dibutuhkan agar nama karyawan otomatis muncul saat insert transaksi,
                                    jadi akun kas baru belum bisa diberikan. Tutup modal ini lalu klik <strong>Edit User</strong> → pilih karyawan via kolom
                                    <em>Karyawan (EMP ID)</em>.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Panel kas: filter + search + grid --}}
                    <div
                        class="p-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- Header panel kas --}}
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-body dark:text-gray-300">
                                Akun Kas yang Dapat Digunakan
                            </h3>
                            @if (count($userKasIds) > 0)
                                {{-- ✅ Cabut semua: x-danger-button + wire:confirm --}}
                                <x-danger-button type="button" wire:click="revokeAll"
                                    wire:confirm="Yakin cabut semua akses kas {{ $userName }}? User tidak akan bisa memproses pembayaran.">
                                    Cabut Semua
                                </x-danger-button>
                            @endif
                        </div>

                        <p class="mb-3 text-xs text-muted dark:text-gray-400">
                            Kas yang dicentang muncul di <strong>semua</strong> layar pembayaran user ini —
                            Kasir RJ, UGD, RI, Apotek RI, Cash In/Out, Penerimaan &amp; Hutang PBF, Piutang.
                        </p>

                        {{-- Search --}}
                        <div class="flex flex-wrap items-center gap-2 mb-4">
                            <x-text-input type="text" wire:model.live.debounce.250ms="searchKas"
                                placeholder="Cari kode / nama kas..." class="flex-1 min-w-[180px]" />
                        </div>

                        {{-- Grid akun kas --}}
                        {{-- ✅ Toggle assign/revoke: raw <button> (inline grid — sesuai standar untuk tombol ikon/aksi inline) --}}
                        @if (count($availableKas) > 0)
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($availableKas as $kas)
                                    @php($kasTerkunci = !$empId && !$kas['assigned'])
                                    <button wire:key="kas-modal-{{ $kas['acc_id'] }}"
                                        wire:click="toggleKas('{{ $kas['acc_id'] }}')" type="button"
                                        @disabled($kasTerkunci)
                                        @if ($kasTerkunci) title="Isi EMP ID dulu lewat Edit User" @endif
                                        class="flex items-center justify-between p-3 text-left transition rounded-xl border
                                            disabled:opacity-50 disabled:cursor-not-allowed
                                            {{ $kas['assigned']
                                                ? 'border-brand-green bg-brand-green/5 dark:bg-brand-green/10 dark:border-brand-green/50'
                                                : 'border-hairline bg-canvas hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600' }}">

                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-sm font-semibold truncate
                                                {{ $kas['assigned'] ? 'text-brand-green dark:text-brand-lime' : 'text-ink dark:text-gray-200' }}">
                                                {{ $kas['acc_name'] ?: $kas['acc_id'] }}
                                            </p>
                                            <p class="text-xs font-mono text-muted-soft">{{ $kas['acc_id'] }}</p>
                                        </div>

                                        <div class="ml-3 shrink-0">
                                            @if ($kas['assigned'])
                                                <svg class="w-5 h-5 text-brand-green dark:text-brand-lime"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            @else
                                                <svg class="w-5 h-5 text-gray-300 dark:text-gray-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="9" stroke-width="1.5" />
                                                </svg>
                                            @endif
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                Tidak ada akun kas ditemukan.
                            </div>
                        @endif
                    </div>

                </div>
            </div>

            {{-- ── FOOTER ── --}}
            {{-- ✅ Standar: sticky bottom-0, tombol di kanan --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    @if (!$empId)
                        <p class="text-xs text-amber-500 dark:text-amber-400">
                            ⚠ Tutup modal ini lalu klik <strong>Edit User</strong> untuk mengisi EMP ID.
                        </p>
                    @else
                        <div></div>
                    @endif

                    {{-- ✅ Tutup footer: x-secondary-button --}}
                    <x-secondary-button wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>

        </div>
    </x-modal>
</div>
