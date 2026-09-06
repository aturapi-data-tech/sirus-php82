<?php
// resources/views/pages/master/master-ews/master-ews-respon-actions.blade.php
//
// FORM respon skor EWS (RSMST_EWS_RESPONS): total skor → kategori, warna,
// frekuensi pantau ulang, respon klinis. Baris cocok bila total di SKOR_MIN..MAX
// ATAU (PARAM_MERAH & ada parameter berskor 3); URUTAN terbesar menang.

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

    public const WARNA = ['PUTIH', 'HIJAU', 'KUNING', 'ORANYE', 'MERAH'];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->form = $this->formKosong('DEWASA');
    }

    private function formKosong(string $varian): array
    {
        return [
            'varian'          => $varian,
            'urutan'          => '',
            'skor_min'        => '',
            'skor_max'        => '',
            'param_merah'     => '0',
            'kategori'        => '',
            'warna'           => 'HIJAU',
            'frekuensi'       => '',
            'frekuensi_menit' => '',
            'respon'          => '',
        ];
    }

    #[On('master.ews.openCreateRespon')]
    public function openCreate(string $varian = 'DEWASA'): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->form = $this->formKosong(array_key_exists($varian, EwsDefault::VARIAN) ? $varian : 'DEWASA');
        $this->form['urutan'] = (string) ((int) DB::table('rsmst_ews_respons')->where('varian', $this->form['varian'])->max('urutan') + 1);

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-ews-respon-actions');
        $this->dispatch('focus-ews-respon-skor-min');
    }

    #[On('master.ews.openEditRespon')]
    public function openEdit(int $responId): void
    {
        $row = DB::table('rsmst_ews_respons')->where('respon_id', $responId)->first();
        if (!$row) {
            $this->dispatch('toast', type: 'error', message: 'Respon tidak ditemukan.');
            return;
        }

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $responId;
        $this->form = [
            'varian'          => (string) $row->varian,
            'urutan'          => (string) $row->urutan,
            'skor_min'        => $row->skor_min === null ? '' : (string) $row->skor_min,
            'skor_max'        => $row->skor_max === null ? '' : (string) $row->skor_max,
            'param_merah'     => (string) $row->param_merah,
            'kategori'        => (string) $row->kategori,
            'warna'           => (string) $row->warna,
            'frekuensi'       => (string) $row->frekuensi,
            'frekuensi_menit' => $row->frekuensi_menit === null ? '' : (string) $row->frekuensi_menit,
            'respon'          => (string) $row->respon,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-ews-respon-actions');
        $this->dispatch('focus-ews-respon-skor-min');
    }

    #[On('master.ews.requestDeleteRespon')]
    public function deleteRespon(int $responId): void
    {
        try {
            $deleted = DB::table('rsmst_ews_respons')->where('respon_id', $responId)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Respon tidak ditemukan.');
                return;
            }
            EwsMaster::flush();

            $this->dispatch('toast', type: 'success', message: 'Respon EWS berhasil dihapus.');
            $this->dispatch('master.ews.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Respon tidak bisa dihapus karena masih dipakai.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $this->validate([
            'form.varian'          => 'required|in:' . implode(',', array_keys(EwsDefault::VARIAN)),
            'form.urutan'          => 'required|integer|min:0|max:999',
            'form.skor_min'        => 'nullable|integer|min:0|max:99',
            'form.skor_max'        => 'nullable|integer|min:0|max:99',
            'form.kategori'        => 'required|string|max:30',
            'form.warna'           => 'required|in:' . implode(',', self::WARNA),
            'form.frekuensi'       => 'required|string|max:50',
            'form.frekuensi_menit' => 'nullable|integer|min:1|max:99999',
            'form.respon'          => 'required|string|max:600',
        ], [
            'required' => ':attribute wajib diisi.',
            'integer'  => ':attribute harus bilangan bulat.',
            'max'      => ':attribute terlalu panjang / besar.',
            'min'      => ':attribute terlalu kecil.',
            'in'       => ':attribute tidak valid.',
        ], [
            'form.varian'          => 'Varian',
            'form.urutan'          => 'Urutan',
            'form.skor_min'        => 'Skor minimal',
            'form.skor_max'        => 'Skor maksimal',
            'form.kategori'        => 'Kategori risiko',
            'form.warna'           => 'Warna',
            'form.frekuensi'       => 'Frekuensi pantau',
            'form.frekuensi_menit' => 'Frekuensi (menit)',
            'form.respon'          => 'Respon klinis',
        ]);

        if ($this->form['skor_min'] === '' && $this->form['skor_max'] === '' && $this->form['param_merah'] !== '1') {
            $this->addError('form.skor_min', 'Isi rentang skor, atau centang "berlaku bila ada parameter merah" — kalau tidak, baris ini tak pernah cocok.');
            return;
        }
        if ($this->form['skor_min'] !== '' && $this->form['skor_max'] !== '' && (int) $this->form['skor_min'] > (int) $this->form['skor_max']) {
            $this->addError('form.skor_max', 'Skor maksimal harus ≥ skor minimal.');
            return;
        }

        $payload = [
            'varian'          => $this->form['varian'],
            'urutan'          => (int) $this->form['urutan'],
            'skor_min'        => $this->form['skor_min'] === '' ? null : (int) $this->form['skor_min'],
            'skor_max'        => $this->form['skor_max'] === '' ? null : (int) $this->form['skor_max'],
            'param_merah'     => $this->form['param_merah'] === '1' ? '1' : '0',
            'kategori'        => trim($this->form['kategori']),
            'warna'           => $this->form['warna'],
            'frekuensi'       => trim($this->form['frekuensi']),
            'frekuensi_menit' => $this->form['frekuensi_menit'] === '' ? null : (int) $this->form['frekuensi_menit'],
            'respon'          => trim($this->form['respon']),
        ];

        DB::transaction(function () use ($payload) {
            if ($this->formMode === 'create') {
                $responId = EwsMaster::idBaru('rsmst_ews_respons', 'respon_id');
                DB::table('rsmst_ews_respons')->insert(['respon_id' => $responId, ...$payload]);
            } else {
                DB::table('rsmst_ews_respons')->where('respon_id', $this->originalId)->update($payload);
            }
        });

        EwsMaster::flush();

        $this->dispatch('toast', type: 'success', message: 'Respon EWS berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.ews.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-ews-respon-actions');
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
    <x-modal name="master-ews-respon-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-ews-respon-actions"
            event="master.ews.saved"
            label="Respon EWS"
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
                                    {{ $formMode === 'edit' ? 'Ubah Respon Skor EWS' : 'Tambah Respon Skor EWS' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    {{ EwsDefault::VARIAN[$form['varian']] ?? $form['varian'] }} — total skor → kategori risiko, frekuensi pantau ulang, respon klinis.
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
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
                 x-data
                 x-on:focus-ews-respon-skor-min.window="$nextTick(() => setTimeout(() => $refs.inputSkorMin?.focus(), 150))">

                <x-border-form title="Respon Skor">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <div>
                            <x-input-label value="Varian" />
                            <x-select-input wire:model="form.varian" class="w-full mt-1" :disabled="$formMode === 'edit'" :error="$errors->has('form.varian')">
                                @foreach (EwsDefault::VARIAN as $kode => $label)
                                    <option value="{{ $kode }}">{{ $label }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.varian')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Urutan (ringan → berat)" />
                            <x-text-input wire:model="form.urutan" type="number" min="0" max="999" :error="$errors->has('form.urutan')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.urutan')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Skor minimal" />
                            <x-text-input wire:model="form.skor_min" x-ref="inputSkorMin" type="number" min="0" max="99" placeholder="kosong = tanpa batas"
                                :error="$errors->has('form.skor_min')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.skor_min')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Skor maksimal" />
                            <x-text-input wire:model="form.skor_max" type="number" min="0" max="99" placeholder="kosong = tanpa batas"
                                :error="$errors->has('form.skor_max')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.skor_max')" class="mt-1" />
                        </div>
                        <div class="flex items-end sm:col-span-2">
                            <x-toggle wire:model.live="form.param_merah" trueValue="1" falseValue="0"
                                label="Juga berlaku bila ada 1 parameter berskor 3 (kode merah)" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label value="Kategori risiko" />
                            <x-text-input wire:model="form.kategori" maxlength="30" placeholder="Rendah / Sedang / Tinggi"
                                :error="$errors->has('form.kategori')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.kategori')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Warna" />
                            <x-select-input wire:model="form.warna" class="w-full mt-1" :error="$errors->has('form.warna')">
                                @foreach (self::WARNA as $warna)
                                    <option value="{{ $warna }}">{{ $warna }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.warna')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Frekuensi pantau (teks)" />
                            <x-text-input wire:model="form.frekuensi" maxlength="50" placeholder="Minimal tiap 1 jam"
                                :error="$errors->has('form.frekuensi')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.frekuensi')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Frekuensi (menit)" />
                            <x-text-input wire:model="form.frekuensi_menit" type="number" min="1" placeholder="60"
                                :error="$errors->has('form.frekuensi_menit')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.frekuensi_menit')" class="mt-1" />
                        </div>

                        <div class="sm:col-span-6">
                            <x-input-label value="Respon klinis" />
                            <x-textarea wire:model="form.respon" rows="3" maxlength="600" :error="$errors->has('form.respon')" class="w-full mt-1"
                                x-on:keydown.enter.prevent="$wire.save()" />
                            <x-input-error :messages="$errors->get('form.respon')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-surface-card border border-hairline rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">di Respon klinis untuk simpan</span>
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
