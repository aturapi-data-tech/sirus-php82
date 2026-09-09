<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Support\Keuangan\SaldoKas;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $accId        = '';
    public string $accDesc      = '';
    public string $accDkStatus  = 'D';
    public string $tanggal      = '';
    /** Shift terakhir yang dihitung pada tanggal ('' = seluruh hari) — ikut induk. */
    public string $shift        = '';
    public string $tahun        = '';
    public string $saldoCurrent = '0';
    public string $saldoTarget  = '0';

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('keuangan.saldo-kas.openEdit')]
    public function openEdit(string $accId, string $tanggal, string $shift = ''): void
    {
        if (!auth()->user()?->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Manager ke atas yang bisa mengedit saldo.');
            return;
        }

        $row = DB::table('acmst_accounts')
            ->select('acc_id', 'acc_name', 'acc_dk_status')
            ->where('acc_id', $accId)
            ->first();

        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Akun kas tidak ditemukan.');
            return;
        }

        $this->accId       = (string) $row->acc_id;
        $this->accDesc     = (string) ($row->acc_name ?? '');
        $this->accDkStatus = (string) ($row->acc_dk_status ?? 'D');
        $this->tanggal     = $tanggal;
        $this->shift       = $shift;
        $this->tahun       = substr($tanggal, 0, 4);

        // Rumus 6i (SaldoKas), termasuk potongan shift, agar sama dengan angka di tabel induk.
        $this->saldoCurrent = (string) SaldoKas::hitung($this->accId, $this->accDkStatus, $tanggal, $shift !== '' ? $shift : null);
        $this->saldoTarget  = $this->saldoCurrent;

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'saldo-kas-actions');
    }

    public function save(): void
    {
        if (!auth()->user()?->hasAnyRole(['Admin', 'Manager Umum', 'Manager Medis'])) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Manager ke atas yang bisa mengedit saldo.');
            return;
        }

        $this->validate([
            'saldoTarget' => 'required|numeric',
        ], [
            'saldoTarget.required' => 'Saldo target wajib diisi.',
            'saldoTarget.numeric'  => 'Saldo target harus berupa angka.',
        ]);

        $target = (float) $this->saldoTarget;
        $tahun  = (int) $this->tahun;

        // Mengikuti legacy: arus_year = sum total tahun ini (Jan–Des), rumus 6i (SaldoKas).
        // updatesaldo = target_saldo - arus_year → simpan ke saldo_awal_tahun.
        $arusYear    = SaldoKas::arusTahun($this->accId, $this->accDkStatus, $tahun);
        $updateSaldo = $target - $arusYear;

        if ($this->accDkStatus === 'D') {

            $exists = DB::table('tktxn_saldoawalakuns')
                ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)->exists();

            if ($exists) {
                DB::table('tktxn_saldoawalakuns')
                    ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)
                    ->update(['sa_acc_d' => $updateSaldo]);
            } else {
                DB::table('tktxn_saldoawalakuns')->insert([
                    'acc_id'   => $this->accId,
                    'sa_year'  => (string) $tahun,
                    'sa_acc_d' => $updateSaldo,
                    'sa_acc_k' => 0,
                ]);
            }
        } else {
            $exists = DB::table('tktxn_saldoawalakuns')
                ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)->exists();

            if ($exists) {
                DB::table('tktxn_saldoawalakuns')
                    ->where('acc_id', $this->accId)->where('sa_year', (string) $tahun)
                    ->update(['sa_acc_k' => $updateSaldo]);
            } else {
                DB::table('tktxn_saldoawalakuns')->insert([
                    'acc_id'   => $this->accId,
                    'sa_year'  => (string) $tahun,
                    'sa_acc_d' => 0,
                    'sa_acc_k' => $updateSaldo,
                ]);
            }
        }

        $this->dispatch('toast', type: 'success',
            message: "Saldo awal tahun {$tahun} di-update untuk akun {$this->accId}.");
        $this->closeModal();
        $this->dispatch('keuangan.saldo-kas.saved');
    }

    public function closeModal(): void
    {
        $this->reset(['accId', 'accDesc', 'accDkStatus',
                      'tanggal', 'shift', 'tahun', 'saldoCurrent', 'saldoTarget']);
        $this->resetValidation();
        $this->dispatch('close-modal', name: 'saldo-kas-actions');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="saldo-kas-actions" focusable>
        <div class="p-6 space-y-5"
             wire:key="{{ $this->renderKey('modal', [$accId, $tanggal]) }}">

            <div>
                <h2 class="text-lg font-semibold text-ink dark:text-gray-100">
                    Edit Saldo Awal Tahun
                </h2>
                <p class="mt-1 text-sm text-muted dark:text-gray-400">
                    Sistem akan back-calc saldo awal tahun {{ $tahun }} agar saldo per
                    {{ $tanggal ? \Carbon\Carbon::parse($tanggal)->format('d/m/Y') : '' }}{{ $shift !== '' ? ' shift ' . $shift : '' }}
                    sama dengan target yang Anda tentukan.
                </p>
            </div>

            <x-border-form title="Konteks">
                <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted dark:text-gray-400">Akun Kas</dt>
                        <dd class="font-medium">
                            <span class="font-mono">{{ $accId }}</span> — {{ $accDesc }}
                            <span class="ml-1 px-1.5 text-[10px] rounded {{ $accDkStatus === 'D' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                {{ $accDkStatus }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted dark:text-gray-400">Saldo Saat Ini</dt>
                        <dd class="font-mono font-medium">
                            Rp {{ number_format((float) $saldoCurrent, 0, '.', ',') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted dark:text-gray-400">Tahun Saldo Awal</dt>
                        <dd class="font-medium">{{ $tahun }}</dd>
                    </div>
                </dl>
            </x-border-form>

            <div>
                <x-input-label for="saldoTarget" value="Saldo Target Per Tanggal" :required="true" />
                <x-text-input id="saldoTarget" type="number" step="0.01"
                    wire:model.live="saldoTarget"
                    :error="$errors->has('saldoTarget')"
                    class="block w-full mt-1 font-mono" />
                <p class="mt-1 text-xs text-muted dark:text-gray-400">
                    Saldo awal tahun akan dihitung mundur agar posisi per tanggal = nilai ini.
                </p>
                <x-input-error :messages="$errors->get('saldoTarget')" class="mt-1" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>Simpan Saldo</span>
                    <span wire:loading>Saving...</span>
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
