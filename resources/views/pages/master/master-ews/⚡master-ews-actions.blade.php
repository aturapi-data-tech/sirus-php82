<?php
// resources/views/pages/master/master-ews/master-ews-actions.blade.php
//
// FORM parameter EWS + sub-list rentang skornya (RSMST_EWS_PARAMS + RSMST_EWS_RENTANGS).
// Rentang disimpan "hapus semua lalu tulis ulang" per parameter — tidak ada yang
// mereferensi RENTANG_ID, jadi id boleh berganti. Setelah simpan, cache master
// (EwsMaster) dibersihkan supaya Observasi Lanjutan langsung memakai ambang baru.

use App\Support\Ews\EwsDefault;
use App\Support\Ews\EwsMaster;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->form = $this->formKosong('DEWASA');
    }

    private function formKosong(string $varian): array
    {
        return [
            'varian'        => $varian,
            'param_kode'    => '',
            'param_desc'    => '',
            'tipe'          => 'ANGKA',
            'satuan'        => '',
            'urutan'        => '',
            'wajib'         => '1',
            'gantikan_kode' => '',
            'active_status' => '1',
            'rentang'       => [],
        ];
    }

    private function rentangKosong(): array
    {
        return [
            'batas_bawah'  => '',
            'batas_atas'   => '',
            'pilihan_kode' => '',
            'pilihan_desc' => '',
            'syarat'       => '',
            'usia_min_bln' => '',
            'usia_max_bln' => '',
            'skor'         => '0',
        ];
    }

    // ─── Open Create ──────────────────────────────────────────────────────────
    #[On('master.ews.openCreate')]
    public function openCreate(string $varian = 'DEWASA'): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->form = $this->formKosong(array_key_exists($varian, EwsDefault::VARIAN) ? $varian : 'DEWASA');
        $this->form['urutan'] = (string) ((int) DB::table('rsmst_ews_params')->where('varian', $this->form['varian'])->max('urutan') + 1);
        $this->form['rentang'][] = $this->rentangKosong();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-ews-actions');
        $this->dispatch('focus-ews-param-kode');
    }

    // ─── Open Edit ────────────────────────────────────────────────────────────
    #[On('master.ews.openEdit')]
    public function openEdit(int $paramId): void
    {
        $row = DB::table('rsmst_ews_params')->where('param_id', $paramId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Parameter tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $paramId;
        $this->form = [
            'varian'        => (string) $row->varian,
            'param_kode'    => (string) $row->param_kode,
            'param_desc'    => (string) $row->param_desc,
            'tipe'          => (string) $row->tipe,
            'satuan'        => (string) ($row->satuan ?? ''),
            'urutan'        => (string) $row->urutan,
            'wajib'         => (string) $row->wajib,
            'gantikan_kode' => (string) ($row->gantikan_kode ?? ''),
            'active_status' => (string) $row->active_status,
            'rentang'       => [],
        ];

        $rentangs = DB::table('rsmst_ews_rentangs')->where('param_id', $paramId)->orderBy('urutan')->orderBy('rentang_id')->get();
        foreach ($rentangs as $rentang) {
            $this->form['rentang'][] = [
                'batas_bawah'  => $rentang->batas_bawah === null ? '' : (string) (float) $rentang->batas_bawah,
                'batas_atas'   => $rentang->batas_atas === null ? '' : (string) (float) $rentang->batas_atas,
                'pilihan_kode' => (string) ($rentang->pilihan_kode ?? ''),
                'pilihan_desc' => (string) ($rentang->pilihan_desc ?? ''),
                'syarat'       => (string) ($rentang->syarat ?? ''),
                'usia_min_bln' => $rentang->usia_min_bln === null ? '' : (string) $rentang->usia_min_bln,
                'usia_max_bln' => $rentang->usia_max_bln === null ? '' : (string) $rentang->usia_max_bln,
                'skor'         => (string) $rentang->skor,
            ];
        }
        if ($this->form['rentang'] === []) {
            $this->form['rentang'][] = $this->rentangKosong();
        }

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-ews-actions');
        $this->dispatch('focus-ews-param-desc');
    }

    // ─── Sub-list rentang ─────────────────────────────────────────────────────
    public function addRentang(): void
    {
        $this->form['rentang'][] = $this->rentangKosong();
    }

    public function removeRentang(int $index): void
    {
        unset($this->form['rentang'][$index]);
        $this->form['rentang'] = array_values($this->form['rentang']);
        if ($this->form['rentang'] === []) {
            $this->form['rentang'][] = $this->rentangKosong();
        }
        $this->resetValidation();
    }

    // ─── Toggle aktif ─────────────────────────────────────────────────────────
    #[On('master.ews.toggleActive')]
    public function toggleActive(int $paramId): void
    {
        $sekarang = (string) DB::table('rsmst_ews_params')->where('param_id', $paramId)->value('active_status');
        if ($sekarang === '') {
            $this->dispatch('toast', type: 'error', message: 'Parameter tidak ditemukan.');
            return;
        }
        $berikutnya = $sekarang === '1' ? '0' : '1';
        DB::table('rsmst_ews_params')->where('param_id', $paramId)->update(['active_status' => $berikutnya]);
        EwsMaster::flush();

        $this->dispatch('toast', type: 'success', message: $berikutnya === '1' ? 'Parameter diaktifkan.' : 'Parameter dinonaktifkan — tidak ikut dihitung.');
        $this->dispatch('master.ews.saved');
    }

    // ─── Delete ───────────────────────────────────────────────────────────────
    #[On('master.ews.requestDelete')]
    public function deleteParam(int $paramId): void
    {
        try {
            $deleted = DB::transaction(function () use ($paramId) {
                DB::table('rsmst_ews_rentangs')->where('param_id', $paramId)->delete();

                return DB::table('rsmst_ews_params')->where('param_id', $paramId)->delete();
            });
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Parameter tidak ditemukan.');
                return;
            }
            EwsMaster::flush();

            $this->dispatch('toast', type: 'success', message: 'Parameter EWS beserta rentangnya berhasil dihapus.');
            $this->dispatch('master.ews.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Parameter tidak bisa dihapus karena masih dipakai.');
                return;
            }
            throw $e;
        }
    }

    // ─── Save ─────────────────────────────────────────────────────────────────
    public function save(): void
    {
        $this->validate($this->rules(), $this->messages(), $this->validationAttributes());

        // Unik (varian, kode) — dicek manual karena rule unique bawaan tak bisa dua kolom + kecualikan diri sendiri.
        $kembar = DB::table('rsmst_ews_params')
            ->where('varian', $this->form['varian'])
            ->whereRaw('UPPER(param_kode) = ?', [mb_strtoupper($this->form['param_kode'])])
            ->when($this->formMode === 'edit', fn($q) => $q->where('param_id', '<>', $this->originalId))
            ->exists();
        if ($kembar) {
            $this->addError('form.param_kode', 'Kode ini sudah dipakai di varian yang sama.');
            return;
        }

        $tipe = $this->form['tipe'];
        foreach ($this->form['rentang'] as $i => $rentang) {
            if ($tipe === 'PILIHAN' && trim($rentang['pilihan_kode']) === '') {
                $this->addError("form.rentang.{$i}.pilihan_kode", 'Kode pilihan wajib diisi untuk tipe PILIHAN.');
                return;
            }
            if ($tipe !== 'PILIHAN' && $rentang['batas_bawah'] === '' && $rentang['batas_atas'] === '') {
                $this->addError("form.rentang.{$i}.batas_bawah", 'Isi batas bawah dan/atau batas atas.');
                return;
            }
            if ($rentang['batas_bawah'] !== '' && $rentang['batas_atas'] !== '' && (float) $rentang['batas_bawah'] > (float) $rentang['batas_atas']) {
                $this->addError("form.rentang.{$i}.batas_atas", 'Batas atas harus ≥ batas bawah.');
                return;
            }
        }

        $payload = [
            'varian'        => $this->form['varian'],
            'param_kode'    => trim($this->form['param_kode']),
            'param_desc'    => trim($this->form['param_desc']),
            'tipe'          => $tipe,
            'satuan'        => trim($this->form['satuan']) === '' ? null : trim($this->form['satuan']),
            'urutan'        => (int) $this->form['urutan'],
            'wajib'         => $this->form['wajib'] === '1' ? '1' : '0',
            'gantikan_kode' => trim($this->form['gantikan_kode']) === '' ? null : trim($this->form['gantikan_kode']),
            'active_status' => $this->form['active_status'] === '1' ? '1' : '0',
        ];

        DB::transaction(function () use ($payload, $tipe) {
            if ($this->formMode === 'create') {
                $paramId = EwsMaster::idBaru('rsmst_ews_params', 'param_id');
                DB::table('rsmst_ews_params')->insert(['param_id' => $paramId, ...$payload]);
            } else {
                $paramId = $this->originalId;
                DB::table('rsmst_ews_params')->where('param_id', $paramId)->update($payload);
                DB::table('rsmst_ews_rentangs')->where('param_id', $paramId)->delete();
            }

            foreach ($this->form['rentang'] as $i => $rentang) {
                $rentangId = EwsMaster::idBaru('rsmst_ews_rentangs', 'rentang_id');
                $isPilihan = $tipe === 'PILIHAN';
                DB::table('rsmst_ews_rentangs')->insert([
                    'rentang_id'   => $rentangId,
                    'param_id'     => $paramId,
                    'urutan'       => $i + 1,
                    'batas_bawah'  => $isPilihan || $rentang['batas_bawah'] === '' ? null : (float) $rentang['batas_bawah'],
                    'batas_atas'   => $isPilihan || $rentang['batas_atas'] === '' ? null : (float) $rentang['batas_atas'],
                    'pilihan_kode' => $isPilihan ? trim($rentang['pilihan_kode']) : null,
                    'pilihan_desc' => trim($rentang['pilihan_desc']) === '' ? null : trim($rentang['pilihan_desc']),
                    'syarat'       => trim($rentang['syarat']) === '' ? null : trim($rentang['syarat']),
                    'usia_min_bln' => $rentang['usia_min_bln'] === '' ? null : (int) $rentang['usia_min_bln'],
                    'usia_max_bln' => $rentang['usia_max_bln'] === '' ? null : (int) $rentang['usia_max_bln'],
                    'skor'         => (int) $rentang['skor'],
                ]);
            }
        });

        EwsMaster::flush();

        $this->dispatch('toast', type: 'success', message: 'Parameter EWS berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.ews.saved');
    }

    protected function rules(): array
    {
        return [
            'form.varian'        => 'required|in:' . implode(',', array_keys(EwsDefault::VARIAN)),
            'form.param_kode'    => 'required|string|max:30|regex:/^[a-zA-Z][a-zA-Z0-9]*$/',
            'form.param_desc'    => 'required|string|max:100',
            'form.tipe'          => 'required|in:ANGKA,PILIHAN,REFERENSI',
            'form.satuan'        => 'nullable|string|max:20',
            'form.urutan'        => 'required|integer|min:0|max:999',
            'form.gantikan_kode' => 'nullable|string|max:30',
            'form.rentang'       => 'required|array|min:1',
            'form.rentang.*.batas_bawah'  => 'nullable|numeric',
            'form.rentang.*.batas_atas'   => 'nullable|numeric',
            'form.rentang.*.pilihan_kode' => 'nullable|string|max:30|regex:/^[A-Za-z0-9_]*$/',
            'form.rentang.*.pilihan_desc' => 'nullable|string|max:300',
            'form.rentang.*.syarat'       => 'nullable|string|max:30',
            'form.rentang.*.usia_min_bln' => 'nullable|integer|min:0|max:9999',
            'form.rentang.*.usia_max_bln' => 'nullable|integer|min:0|max:9999',
            'form.rentang.*.skor'         => 'required|integer|min:0|max:9',
        ];
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'integer'  => ':attribute harus bilangan bulat.',
            'numeric'  => ':attribute harus angka.',
            'max'      => ':attribute terlalu panjang / besar.',
            'min'      => ':attribute terlalu kecil.',
            'in'       => ':attribute tidak valid.',
            'form.param_kode.regex'          => 'Kode harus camelCase huruf/angka tanpa spasi (mis. frekuensiNafas).',
            'form.rentang.*.pilihan_kode.regex' => 'Kode pilihan hanya huruf, angka, garis bawah (mis. ROOM_AIR).',
            'form.rentang.min'               => 'Minimal satu rentang skor.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.varian'        => 'Varian',
            'form.param_kode'    => 'Kode JSON',
            'form.param_desc'    => 'Nama parameter',
            'form.tipe'          => 'Tipe',
            'form.satuan'        => 'Satuan',
            'form.urutan'        => 'Urutan',
            'form.gantikan_kode' => 'Menggantikan kode',
            'form.rentang'       => 'Rentang skor',
            'form.rentang.*.batas_bawah'  => 'Batas bawah',
            'form.rentang.*.batas_atas'   => 'Batas atas',
            'form.rentang.*.pilihan_kode' => 'Kode pilihan',
            'form.rentang.*.pilihan_desc' => 'Label',
            'form.rentang.*.syarat'       => 'Syarat',
            'form.rentang.*.usia_min_bln' => 'Usia min (bln)',
            'form.rentang.*.usia_max_bln' => 'Usia max (bln)',
            'form.rentang.*.skor'         => 'Skor',
        ];
    }

    // ─── Close ────────────────────────────────────────────────────────────────
    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-ews-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = $this->formKosong('DEWASA');
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-ews-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-ews-actions"
            event="master.ews.saved"
            label="Parameter EWS"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="ds-display-sm dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Parameter EWS' : 'Tambah Parameter EWS' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    {{ EwsDefault::VARIAN[$form['varian']] ?? $form['varian'] }} — parameter yang dinilai beserta rentang nilai → skor.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 space-y-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
                 x-data
                 x-on:focus-ews-param-kode.window="$nextTick(() => setTimeout(() => $refs.inputParamKode?.focus(), 150))"
                 x-on:focus-ews-param-desc.window="$nextTick(() => setTimeout(() => $refs.inputParamDesc?.focus(), 150))">

                <x-border-form title="Parameter">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <div>
                            <x-input-label value="Varian" />
                            <x-select-input wire:model.live="form.varian" class="w-full mt-1" :disabled="$formMode === 'edit'" :error="$errors->has('form.varian')">
                                @foreach (EwsDefault::VARIAN as $kode => $label)
                                    <option value="{{ $kode }}">{{ $label }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.varian')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Kode JSON" />
                            <x-text-input wire:model="form.param_kode" x-ref="inputParamKode" maxlength="30"
                                placeholder="frekuensiNafas" :error="$errors->has('form.param_kode')" class="w-full mt-1 font-mono" />
                            <x-input-error :messages="$errors->get('form.param_kode')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Nama parameter" />
                            <x-text-input wire:model="form.param_desc" x-ref="inputParamDesc" maxlength="100"
                                :error="$errors->has('form.param_desc')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.param_desc')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Tipe" />
                            <x-select-input wire:model.live="form.tipe" class="w-full mt-1" :error="$errors->has('form.tipe')">
                                <option value="ANGKA">ANGKA — rentang nilai</option>
                                <option value="PILIHAN">PILIHAN — petugas memilih</option>
                                <option value="REFERENSI">REFERENSI — acuan, tidak diskor</option>
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.tipe')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Satuan" />
                            <x-text-input wire:model="form.satuan" maxlength="20" placeholder="x/mnt, %, mmHg" class="w-full mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Urutan" />
                            <x-text-input wire:model="form.urutan" type="number" min="0" max="999" :error="$errors->has('form.urutan')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.urutan')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Menggantikan kode" />
                            <x-text-input wire:model="form.gantikan_kode" maxlength="30" placeholder="spo2" class="w-full mt-1 font-mono" />
                            <p class="mt-1 text-xs text-muted-soft">Bila param ini diisi, kode tersebut dilewati (SpO₂ skala 2 → spo2).</p>
                        </div>
                        <div class="flex items-end gap-6 sm:col-span-2">
                            <x-toggle wire:model.live="form.wajib" trueValue="1" falseValue="0" label="Wajib diisi" />
                            <x-toggle wire:model.live="form.active_status" trueValue="1" falseValue="0" label="Aktif" />
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="{{ $form['tipe'] === 'PILIHAN' ? 'Pilihan → Skor' : ($form['tipe'] === 'REFERENSI' ? 'Rentang acuan per usia' : 'Rentang nilai → Skor') }}">
                    <p class="mb-3 text-xs text-muted dark:text-gray-400">
                        @if ($form['tipe'] === 'PILIHAN')
                            Satu baris = satu pilihan. <b>Kode</b> tersimpan di JSON EMR (huruf besar, garis bawah), <b>Label</b> yang dilihat perawat.
                        @else
                            Batas <b>inklusif</b> dua sisi; kosongkan batas bawah untuk "<= X", kosongkan batas atas untuk ">= X". Tulis label dengan huruf biasa: <b>&lt;= 8</b>, <b>&gt;= 25</b>, <b>SpO2</b>. Simbol matematika dan angka kecil (subskrip) tidak bisa disimpan Oracle.
                            <b>Syarat</b> = kode pilihan parameter lain yang harus terpilih (mis. O2). <b>Usia</b> (bulan) hanya untuk baris acuan per usia.
                        @endif
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs font-semibold text-muted uppercase bg-surface-soft dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th class="px-2 py-2 text-left w-8">#</th>
                                    @if ($form['tipe'] === 'PILIHAN')
                                        <th class="px-2 py-2 text-left w-44">Kode</th>
                                        <th class="px-2 py-2 text-left">Label</th>
                                    @else
                                        <th class="px-2 py-2 text-left w-28">Batas bawah</th>
                                        <th class="px-2 py-2 text-left w-28">Batas atas</th>
                                        <th class="px-2 py-2 text-left">Label (opsional)</th>
                                        <th class="px-2 py-2 text-left w-28">Syarat</th>
                                        <th class="px-2 py-2 text-left w-24">Usia min</th>
                                        <th class="px-2 py-2 text-left w-24">Usia max</th>
                                    @endif
                                    <th class="px-2 py-2 text-left w-20">Skor</th>
                                    <th class="px-2 py-2 w-12"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                @foreach ($form['rentang'] as $i => $rentang)
                                    <tr wire:key="ews-rentang-{{ $i }}">
                                        <td class="px-2 py-1.5 text-muted">{{ $i + 1 }}</td>
                                        @if ($form['tipe'] === 'PILIHAN')
                                            <td class="px-2 py-1.5">
                                                <x-text-input wire:model="form.rentang.{{ $i }}.pilihan_kode" maxlength="30" placeholder="ROOM_AIR"
                                                    :error="$errors->has('form.rentang.' . $i . '.pilihan_kode')" class="w-full font-mono uppercase" />
                                                <x-input-error :messages="$errors->get('form.rentang.' . $i . '.pilihan_kode')" class="mt-1" />
                                            </td>
                                            <td class="px-2 py-1.5">
                                                <x-text-input wire:model="form.rentang.{{ $i }}.pilihan_desc" maxlength="300" class="w-full" />
                                            </td>
                                        @else
                                            <td class="px-2 py-1.5">
                                                <x-text-input wire:model="form.rentang.{{ $i }}.batas_bawah" type="number" step="0.1" placeholder="≤"
                                                    :error="$errors->has('form.rentang.' . $i . '.batas_bawah')" class="w-full" />
                                                <x-input-error :messages="$errors->get('form.rentang.' . $i . '.batas_bawah')" class="mt-1" />
                                            </td>
                                            <td class="px-2 py-1.5">
                                                <x-text-input wire:model="form.rentang.{{ $i }}.batas_atas" type="number" step="0.1" placeholder="≥"
                                                    :error="$errors->has('form.rentang.' . $i . '.batas_atas')" class="w-full" />
                                                <x-input-error :messages="$errors->get('form.rentang.' . $i . '.batas_atas')" class="mt-1" />
                                            </td>
                                            <td class="px-2 py-1.5">
                                                <x-text-input wire:model="form.rentang.{{ $i }}.pilihan_desc" maxlength="300" class="w-full" />
                                            </td>
                                            <td class="px-2 py-1.5">
                                                <x-text-input wire:model="form.rentang.{{ $i }}.syarat" maxlength="30" placeholder="O2" class="w-full font-mono uppercase" />
                                            </td>
                                            <td class="px-2 py-1.5">
                                                <x-text-input wire:model="form.rentang.{{ $i }}.usia_min_bln" type="number" min="0" class="w-full" />
                                            </td>
                                            <td class="px-2 py-1.5">
                                                <x-text-input wire:model="form.rentang.{{ $i }}.usia_max_bln" type="number" min="0" class="w-full" />
                                            </td>
                                        @endif
                                        <td class="px-2 py-1.5">
                                            <x-text-input wire:model="form.rentang.{{ $i }}.skor" type="number" min="0" max="9"
                                                :error="$errors->has('form.rentang.' . $i . '.skor')" class="w-full" />
                                            <x-input-error :messages="$errors->get('form.rentang.' . $i . '.skor')" class="mt-1" />
                                        </td>
                                        <td class="px-2 py-1.5 text-center">
                                            <x-outline-button type="button" wire:click.prevent="removeRentang({{ $i }})"
                                                wire:loading.attr="disabled" title="Hapus baris"
                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30 dark:hover:!bg-red-900/30 dark:hover:!text-red-300">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </x-outline-button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <x-secondary-button type="button" wire:click.prevent="addRentang">+ Tambah baris</x-secondary-button>
                    </div>
                    <x-input-error :messages="$errors->get('form.rentang')" class="mt-2" />
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        Setelah simpan, cache master dibersihkan — Observasi Lanjutan langsung memakai ambang baru.
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>Simpan</span>
                            <span wire:loading>Saving...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
