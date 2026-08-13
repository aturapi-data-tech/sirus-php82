<?php

use Livewire\Component;
use Livewire\Attributes\On;

// Tutorial standarisasi koding modul MASTER — versi web dari docs/standar-master-module.md.
// Gaya navigasi per-submenu (sidebar kiri) seperti situs dokumentasi Livewire.
// Semua contoh kode disimpan sebagai nowdoc di sini agar TIDAK dikompilasi Blade
// (tag komponen / direktif di dalam string PHP aman dari compiler).
new class extends Component {
    // State & aksi demo untuk live preview di bab Pemakaian Komponen.
    // Semua no-op — tidak ada data yang ditulis.
    public string $demoText = '';
    public string $demoSelect = '';
    public ?string $demoNumber = '0';

    public function resetFilters(): void
    {
        $this->reset(['demoText', 'demoSelect']);
        $this->demoNumber = '0';
    }

    public function demoAksi(): void
    {
        $this->dispatch('toast', type: 'success', message: 'Aksi demo dijalankan — tidak ada data yang berubah.');
    }

    // Listener demo LOV (bab 08) — persis pola parent sungguhan, hanya menampung payload.
    public string $demoLovId = '';
    public string $demoLovName = '';

    #[On('lov.selected.demo-koding-master')]
    public function onDemoLovSelected(string $target, array $payload): void
    {
        $this->demoLovId   = (string) ($payload['product_id'] ?? '');
        $this->demoLovName = (string) ($payload['product_name'] ?? '');
    }

    public function snippets(): array
    {
        return [

'tree' => <<<'TXT'
resources/views/pages/master/master-<nama>/
├── ⚡master-<nama>.blade.php           # LIST : tabel + toolbar + pagination
└── ⚡master-<nama>-actions.blade.php   # FORM : modal create/edit + delete handler
TXT,

'route' => <<<'TXT'
// routes/web.php — di dalam group middleware(['auth'])
Route::livewire('/master/<nama>', 'pages::master.master-<nama>.master-<nama>')
    ->name('master.<nama>');
TXT,

'mount' => <<<'TXT'
{{-- di paling bawah markup LIST: mount FORM sebagai child --}}
<livewire:pages::master.master-<nama>.master-<nama>-actions
    wire:key="master-<nama>-actions" />
TXT,

'alur-salin' => <<<'TXT'
# dari root repo — contoh membuat master baru "pekerjaan"
cp -r resources/views/pages/master/master-agama \
      resources/views/pages/master/master-pekerjaan

cd resources/views/pages/master/master-pekerjaan
mv ⚡master-agama.blade.php         ⚡master-pekerjaan.blade.php
mv ⚡master-agama-actions.blade.php ⚡master-pekerjaan-actions.blade.php

# lalu di DALAM kedua file, cari-ganti manual (jangan sed membabi-buta):
#   master-agama       → master-pekerjaan     (nama komponen child + nama modal)
#   master.agama.*     → master.pekerjaan.*   (namespace event = nama folder)
#   rsmst_religions    → nama tabel barumu
#   rel_id / rel_desc  → kolom barumu (key $form = nama kolom DB)
#   "Agama"            → label Indonesia barumu (judul, pesan validasi, toast)
TXT,

'alur-route-menu' => <<<'TXT'
// 1) routes/web.php — di dalam group Route::middleware(['auth'])
Route::livewire('/master/pekerjaan', 'pages::master.master-pekerjaan.master-pekerjaan')
    ->name('master.pekerjaan');

// 2) app/Services/AppMenu.php — supaya modul muncul di menu dashboard (difilter role)
$entry([
    'group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 13,
    'route' => 'master.pekerjaan', 'title' => 'Master Pekerjaan',
    'desc'  => 'Kelola data pekerjaan pasien',
    'roles' => $masterRoles, 'badge' => 'Pelayanan',
]),
TXT,

'event-flow' => <<<'TXT'
LIST ──dispatch('master.agama.openEdit', relId: 12)──▶ FORM   (#[On] buka modal)
FORM ──simpan ok → dispatch('master.agama.saved')  ──▶ LIST   (#[On] resetPage)
TXT,

'list-class' => <<<'TXT'
new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    // LIST hanya MENYURUH — tidak pernah menyimpan/validasi sendiri.
    public function openCreate(): void
    {
        $this->dispatch('master.agama.openCreate');
    }

    public function openEdit(int $relId): void
    {
        $this->dispatch('master.agama.openEdit', relId: $relId);
    }

    public function requestDelete(int $relId): void
    {
        $this->dispatch('master.agama.requestDelete', relId: $relId);
    }

    #[On('master.agama.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('rsmst_religions')
            ->select('rel_id', 'rel_desc')
            ->orderBy('rel_id');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->whereRaw('UPPER(rel_desc) LIKE ?', ["%{$kw}%"]);
        }

        return $q->paginate($this->itemsPerPage);
    }
};
TXT,

'toolbar' => <<<'TXT'
{{-- TOOLBAR sticky di atas card tabel --}}
<div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20
            dark:bg-gray-900 dark:border-gray-700">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="w-full lg:max-w-md">
            <x-text-input wire:model.live.debounce.300ms="searchKeyword"
                placeholder="Cari..." class="block w-full" />
        </div>
        <div class="flex items-center justify-end gap-2">
            <div class="w-28">
                <x-select-input wire:model.live="itemsPerPage">
                    <option value="10">10</option>
                    <option value="20">20</option>
                </x-select-input>
            </div>
            <x-primary-button type="button" wire:click="openCreate">
                + Tambah Data
            </x-primary-button>
            <x-toolbar-refresh-reset :label="null" />
        </div>
    </div>
</div>
TXT,

'table' => <<<'TXT'
<table class="ds-table">
    <thead class="sticky top-0 z-10">
        <tr>
            <th>ID</th>
            <th>Agama</th>
            <th class="ds-c">Aksi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($this->rows as $row)
            <tr wire:key="agama-{{ $row->rel_id }}">
                <td class="ds-td-token">{{ $row->rel_id }}</td>
                <td class="ds-td-strong">{{ $row->rel_desc }}</td>
                <td class="ds-c">
                    <div class="flex justify-center gap-2">
                        <x-action-edit wire:click="openEdit({{ $row->rel_id }})" />
                        <x-action-delete
                            :action="'requestDelete(' . $row->rel_id . ')'"
                            title="Hapus Agama"
                            message="Yakin hapus agama {{ $row->rel_desc }}?" />
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3" class="px-6 py-10">
                    {{-- ikon + teks: "Data agama tidak ditemukan." --}}
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
TXT,

'form-class' => <<<'TXT'
new class extends Component {
    use WithRenderVersioningTrait;   // renderKey → modal remount bersih tiap buka

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'rel_id'   => '',
        'rel_desc' => '',
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.agama.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-agama-actions');
        $this->dispatch('focus-rel-id');
    }

    #[On('master.agama.openEdit')]
    public function openEdit(int $relId): void
    {
        $row = DB::table('rsmst_religions')->where('rel_id', $relId)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $relId;
        $this->form = [
            'rel_id'   => (string) $row->rel_id,
            'rel_desc' => (string) ($row->rel_desc ?? ''),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-agama-actions');
        $this->dispatch('focus-rel-desc');
    }

    public function save(): void
    {
        // validate() SELALU duluan — jangan ada early-return sebelum ini,
        // kalau tidak border merah field wajib tak pernah muncul.
        $this->validate($rules, $messages, $attributes);

        $payload = ['rel_desc' => mb_strtoupper($this->form['rel_desc'])];

        if ($this->formMode === 'create') {
            DB::table('rsmst_religions')
                ->insert(['rel_id' => (int) $this->form['rel_id'], ...$payload]);
        } else {
            DB::table('rsmst_religions')
                ->where('rel_id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Data berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.agama.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-agama-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['rel_id' => '', 'rel_desc' => ''];
        $this->resetValidation();
    }
};
TXT,

'modal' => <<<'TXT'
<x-modal name="master-agama-actions" size="full" height="full" focusable>
    <x-dirty-modal-content
        name="master-agama-actions"
        event="master.agama.saved"
        label="Agama"
        :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

        {{-- HEADER: logo + judul Tambah/Ubah + badge Mode + close X (tryClose) --}}

        {{-- BODY: bg-surface-soft + x-enter-chain + fokus via event window --}}
        <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
             x-data
             x-on:focus-rel-id.window="$nextTick(() => setTimeout(() => $refs.inputRelId?.focus(), 150))">

            <x-border-form title="Data Agama">
                <div>
                    <x-input-label value="Nama Agama" />
                    <x-text-input wire:model.live="form.rel_desc" x-ref="inputRelDesc"
                        :error="$errors->has('form.rel_desc')"
                        class="w-full mt-1"
                        x-on:keydown.enter.prevent="$wire.save()" />
                    <x-input-error :messages="$errors->get('form.rel_desc')" class="mt-1" />
                </div>
            </x-border-form>
        </div>

        {{-- FOOTER: sticky bottom — hint Enter · Batal (tryClose) · Simpan --}}
        <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline">
            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                    <span wire:loading.remove>Simpan</span>
                    <span wire:loading>Saving...</span>
                </x-primary-button>
            </div>
        </div>

    </x-dirty-modal-content>
</x-modal>
TXT,

'validasi-inline' => <<<'TXT'
// FORM KECIL (≤ ±5 field): tiga array inline di save() — gaya baseline master-agama
$rules = [
    'form.rel_id'   => $this->formMode === 'create'
        ? 'required|integer|min:1|max:99|unique:rsmst_religions,rel_id'
        : 'required|integer',
    'form.rel_desc' => 'required|string|max:15',
];

$messages = [
    'form.rel_id.required'   => 'ID Agama wajib diisi.',
    'form.rel_id.unique'     => 'ID Agama sudah digunakan.',
    'form.rel_desc.required' => 'Nama Agama wajib diisi.',
    'form.rel_desc.max'      => 'Nama Agama maksimal 15 karakter.',
];

$attributes = [
    'form.rel_id'   => 'ID Agama',
    'form.rel_desc' => 'Nama Agama',
];

$this->validate($rules, $messages, $attributes);
TXT,

'validasi-method' => <<<'TXT'
// FORM BESAR: pisahkan jadi method supaya save() tetap pendek
// (gaya master-poli / master-diagnosa / master-karyawan — resmi, bukan deviasi)
protected function rules(): array
{
    return [ /* ... */ ];
}

protected function messages(): array
{
    return [ /* pesan Bahasa Indonesia ... */ ];
}

protected function validationAttributes(): array
{
    return [ /* nama field manusiawi ... */ ];
}

public function save(): void
{
    $this->validate();   // otomatis pakai ketiga method di atas
    // ...
}
TXT,

'delete' => <<<'TXT'
#[On('master.agama.requestDelete')]
public function deleteAgama(int $relId): void
{
    try {
        $deleted = DB::table('rsmst_religions')->where('rel_id', $relId)->delete();
        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Data tidak ditemukan.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Agama berhasil dihapus.');
        $this->dispatch('master.agama.saved');
    } catch (QueryException $e) {
        // Lapis 2: guard FK Oracle — tanpa ini user kena error 500
        if (str_contains($e->getMessage(), 'ORA-02292')) {
            $this->dispatch('toast', type: 'error',
                message: 'Agama tidak bisa dihapus karena masih dipakai di data pasien.');
            return;
        }
        throw $e;
    }
}
TXT,

'c-page-title' => <<<'TXT'
{{-- selalu paling atas markup list; title & subtitle plain text (tanpa HTML) --}}
<x-page-title
    title="Master Agama"
    subtitle="Kelola data agama pasien" />
TXT,

'c-input' => <<<'TXT'
{{-- trio wajib per field: label + input + error --}}
<div>
    <x-input-label value="Nama Agama" :required="true" />
    <x-text-input wire:model.live="form.rel_desc" x-ref="inputRelDesc"
        maxlength="15"
        :error="$errors->has('form.rel_desc')"
        class="w-full mt-1"
        x-on:keydown.enter.prevent="$wire.save()" />
    <x-input-error :messages="$errors->get('form.rel_desc')" class="mt-1" />
</div>

{{-- dropdown --}}
<x-select-input wire:model.live="form.kategori" :error="$errors->has('form.kategori')">
    <option value="">— pilih —</option>
    <option value="A">Kategori A</option>
</x-select-input>
TXT,

'c-number' => <<<'TXT'
{{-- SEMUA field nominal uang (harga/tarif/biaya) wajib komponen ini.
     Format ribuan otomatis saat display, sync integer bersih saat blur.
     Pakai wire:model TANPA .live — komponen sync via $wire.set() saat blur. --}}
<x-text-input-number wire:model="form.harga"
    :error="$errors->has('form.harga')"
    class="w-full mt-1"
    x-on:keydown.enter.prevent="$refs.inputBerikutnya?.focus()" />
TXT,

'c-border-form' => <<<'TXT'
{{-- pengelompok field di body modal — JANGAN bikin card manual div+h3 --}}
<x-border-form title="Data Agama">
    <div class="space-y-4">
        {{-- fields --}}
    </div>
</x-border-form>

{{-- dua section berdampingan --}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <x-border-form title="Data Dokter"> ... </x-border-form>
    <x-border-form title="Tarif & Administrasi"> ... </x-border-form>
</div>
TXT,

'c-actions' => <<<'TXT'
{{-- kolom Aksi baris tabel — selalu pasangan ini, jangan tombol manual --}}
<x-action-edit wire:click="openEdit({{ $row->rel_id }})" />
<x-action-edit wire:click="openEdit({{ $row->rel_id }})">Ubah</x-action-edit>

<x-action-delete
    :action="'requestDelete(' . $row->rel_id . ')'"
    title="Hapus Agama"
    message="Yakin hapus agama {{ $row->rel_desc }}?" />
TXT,

'c-refresh' => <<<'TXT'
{{-- icon-only (standar toolbar list) — memanggil resetFilters() di komponen --}}
<x-toolbar-refresh-reset :label="null" />

{{-- varian dgn teks + method reset custom --}}
<x-toolbar-refresh-reset label="Aksi" resetAction="resetSemua" :iconOnly="false" />
TXT,

'c-modal' => <<<'TXT'
<x-modal name="master-agama-actions" size="full" height="full" focusable>
    <x-dirty-modal-content
        name="master-agama-actions"                              {{-- = nama modal --}}
        event="master.agama.saved"                               {{-- event reset dirty --}}
        label="Agama"                                            {{-- teks di dialog peringatan --}}
        :wireKey="$this->renderKey('modal', [$formMode, $originalId])">
        {{-- header / body / footer --}}
    </x-dirty-modal-content>
</x-modal>

{{-- tombol tutup di dalam dirty-modal-content SELALU lewat tryClose() --}}
<x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
TXT,

'c-badge' => <<<'TXT'
{{-- badge Mode di header modal --}}
<x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
    {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
</x-badge>
TXT,

'lov-mount' => <<<'TXT'
{{-- contoh nyata: master-obat-kronis-actions.
     Mode tambah = LOV; mode edit = field readonly (atau kirim initial-product-id). --}}
@if ($formMode === 'create')
    <div>
        <livewire:lov.product.lov-product
            target="master-obat-kronis"
            label="Obat (cari dari master obat)"
            placeholder="Ketik nama/kode/kandungan obat..."
            wire:key="lov-master-obat-kronis-{{ $renderVersions['modal'] ?? 0 }}" />

        {{-- error tetap milik field parent, bukan milik LOV --}}
        <x-input-error :messages="$errors->get('form.product_id')" class="mt-1" />

        @if ($form['product_id'] !== '')
            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Terpilih: <span class="font-mono">{{ $form['product_id'] }}</span>
                — {{ $form['product_name'] }}
            </p>
        @endif
    </div>
@endif
TXT,

'lov-listener' => <<<'TXT'
// Nama event SELALU 'lov.selected.' . target — target harus unik per pemakaian.
#[On('lov.selected.master-obat-kronis')]
public function onProductSelected(string $target, array $payload): void
{
    $this->form['product_id']   = (string) ($payload['product_id'] ?? '');
    $this->form['product_name'] = (string) ($payload['product_name'] ?? '');
    $this->resetValidation('form.product_id');
}

// Validasi TETAP di parent — payload LOV tidak dipercaya mentah-mentah:
'form.product_id' => ['required', Rule::exists('immst_products', 'product_id')],
TXT,

'lov-anatomy' => <<<'TXT'
Props umum (semua LOV):
  target       pembeda pemakaian → jadi suffix event lov.selected.<target>
  label        judul field         placeholder   hint di input
  readonly     kunci LOV (form terkunci / mode lihat)
  initial*Id   mode edit — LOV mount langsung dalam keadaan terpilih

Perilaku bawaan (tidak perlu dikoding ulang):
  • ketik ≥ 2 huruf → cari (debounce 250ms, UPPER LIKE Oracle)
  • ketik ID persis (angka) → langsung auto-terpilih
  • keyboard: ↓ / ↑ navigasi · Enter ambil · Esc tutup
  • setelah terpilih → tampil nama + tombol "Ubah" (clearSelected)
TXT,

'level-kamar' => <<<'TXT'
// LEVEL 3 — hierarki induk-anak (master-kamar: bangsal → kamar → bed).
// Namespace event tetap satu (master.kamar.*) tapi VERB-nya spesifik per entitas:

public function openCreateKamar(): void { ... }          // bukan openCreate generik
public function openCreateBed(string $roomId): void { ... }
public function requestDeleteBed(string $bedNo, string $roomId): void { ... }

// List anak mendengarkan pilihan induk:
#[On('bangsal.selected')]
public function onBangsalSelected(string $bangsalId, string $bangsalName): void
{
    // set konteks bangsal aktif → computed rooms() otomatis terfilter
}

// Event saved membawa KONTEKS supaya refresh-nya presisi:
#[On('master.kamar.saved')]
public function afterSaved(string $entity, string $roomId = ''): void
{
    // entity 'kamar' → refresh list; entity 'bed' → cukup refresh panel detail
}
TXT,

'level-kamar-bed-actions' => <<<'TXT'
// CRUD UTUH SATU ENTITAS ANAK — ⚡master-bed-actions.blade.php (dipadatkan).
// Polanya sama persis dgn form bab 06; bedanya: KONTEKS INDUK (room_id)
// ikut di state, di validasi, dan di payload event saved.

public array $formBed = ['bed_no' => '', 'bed_desc' => '', 'room_id' => ''];

#[On('master.kamar.openCreateBed')]
public function openCreateBed(string $roomId): void
{
    $this->resetAll();
    $this->formMode           = 'create';
    $this->formBed['room_id'] = $roomId;          // konteks induk dikunci sejak awal
    $this->incrementVersion('modal');
    $this->dispatch('open-modal', name: 'master-kamar-bed');
    $this->dispatch('focus-bed-no');
}

#[On('master.kamar.openEditBed')]
public function openEditBed(string $bedNo, string $roomId): void
{
    $row = DB::table('rsmst_beds')
        ->where('bed_no', $bedNo)->where('room_id', $roomId)->first();
    if (! $row) return;
    // ... isi $formBed dari $row, formMode = 'edit', buka modal (spt bab 06)
}

public function save(): void
{
    $this->validate($rules, [], $attributes);     // validate() tetap paling atas

    if ($this->formMode === 'create') {
        // PK komposit (bed_no + room_id) → cek duplikat manual, bukan rule unique:
        $exists = DB::table('rsmst_beds')
            ->where('bed_no', $this->formBed['bed_no'])
            ->where('room_id', $this->formBed['room_id'])->exists();
        if ($exists) {
            $this->addError('formBed.bed_no', 'No Bed sudah ada di kamar ini.');
            return;
        }
        DB::table('rsmst_beds')->insert([ /* bed_no + bed_desc + room_id */ ]);
    } else {
        DB::table('rsmst_beds')
            ->where('bed_no', $this->formBed['bed_no'])
            ->where('room_id', $this->formBed['room_id'])
            ->update(['bed_desc' => $this->formBed['bed_desc'] ?: null]);
    }

    $roomId = $this->formBed['room_id'];          // ambil dulu — closeModal() me-reset form
    $this->dispatch('toast', type: 'success', message: 'Data bed berhasil disimpan.');
    $this->closeModal();
    $this->dispatch('master.kamar.saved', entity: 'bed', roomId: $roomId);
}
TXT,

'level-kamar-delete-guard' => <<<'TXT'
// DELETE INDUK — ⚡master-bangsal-actions.blade.php.
// Lapis tambahan khas hierarki: cek anak langsung SEBELUM delete supaya
// pesannya spesifik; ORA-02292 tetap ditangkap sbg jaring pengaman FK lain.

#[On('master.kamar.deleteBangsal')]
public function deleteBangsal(string $bangsalId): void
{
    try {
        $hasRooms = DB::table('rsmst_rooms')->where('bangsal_id', $bangsalId)->exists();
        if ($hasRooms) {
            $this->dispatch('toast', type: 'error',
                message: 'Bangsal tidak bisa dihapus karena masih memiliki kamar.');
            return;
        }

        $deleted = DB::table('rsmst_bangsals')->where('bangsal_id', $bangsalId)->delete();
        if ($deleted === 0) {
            $this->dispatch('toast', type: 'error', message: 'Data bangsal tidak ditemukan.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Bangsal berhasil dihapus.');
        $this->dispatch('master.kamar.saved', entity: 'bangsal');
    } catch (QueryException $e) {
        if (str_contains($e->getMessage(), 'ORA-02292')) {
            $this->dispatch('toast', type: 'error',
                message: 'Bangsal tidak bisa dihapus karena masih dipakai di data lain.');
            return;
        }
        throw $e;
    }
}
TXT,

'level-kamar-refresh' => <<<'TXT'
// REFRESH PRESISI — sisi list (⚡master-kamar.blade.php).
// SATU listener utk semua entitas; payload menentukan bagian mana yang
// disegarkan — save bed tidak perlu memuat ulang tabel kamar.

#[On('master.kamar.saved')]
public function afterSaved(string $entity, string $roomId = ''): void
{
    if ($entity === 'kamar') {
        unset($this->computedPropertyCache);          // buang cache computed rooms()
        $this->resetPage('pageKamar');
        if ($this->selectedRoomId) {
            $this->selectRoom($this->selectedRoomId); // segarkan panel detail
        }
    }

    if ($entity === 'bed' && $roomId) {
        if ($roomId === $this->selectedRoomId) {
            $this->loadBeds();                        // cukup panel bed, bukan seluruh list
        }
        unset($this->computedPropertyCache);
    }
}
TXT,

'level-jm' => <<<'TXT'
// LEVEL 3 — sub-list di dalam form (master-jasa-medis: paket obat/lain-lain).
// Tiap sub-form punya LOV sendiri (target unik) + validate() BERTAHAP sendiri:

#[On('lov.selected.paket-obat-master-jm')]
public function onObatSelected(?array $payload): void { ... }

public function addPaketObat(): void
{
    $this->validate($rulesPaketObat, $messagesPaketObat); // HANYA field sub-form
    $this->form['paketObat'][] = [ /* payload LOV + qty */ ];
    // reset field sub-form utk entri berikutnya
}

public function removePaketObat(int $idx): void
{
    unset($this->form['paketObat'][$idx]);
    $this->form['paketObat'] = array_values($this->form['paketObat']);
}

public function save(): void
{
    $this->validate($rules, $messages);   // validasi form UTAMA (terakhir)
    // simpan header, lalu loop insert baris detail paket
}
TXT,

'pasien-tree' => <<<'TXT'
master-pasien/
├── ⚡master-pasien.blade.php                       # LIST
├── ⚡master-pasien-actions.blade.php               # state + save + tab (Alpine activeTab)
├── master-pasien-actions-identitas.blade.php       # partial murni (tanpa kelas Volt)
├── master-pasien-actions-alamat-identitas.blade.php
├── master-pasien-actions-data-sosial.blade.php
└── ...                                             # satu partial per section/tab
TXT,

        ];
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snip = $this->snippets();

        // Sidebar per-submenu (gaya docs Livewire). Key = id section di bawah.
        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'alur'        => 'Alur: Buat Master Baru',
                'struktur'    => 'Struktur File & Routing',
                'penamaan'    => 'Kontrak Penamaan',
            ],
            'Komponen' => [
                'list'     => 'Halaman List',
                'form'     => 'Form Modal (Actions)',
                'komponen' => 'Pemakaian Komponen',
                'lov'      => 'LOV (List of Values)',
                'anatomi'  => 'Anatomi Visual (UI/UX)',
            ],
            'Aturan' => [
                'validasi' => 'Validasi',
                'delete'   => 'Delete & ORA-02292',
                'partial'  => 'Ukuran File & Partial',
            ],
            'Lanjutan' => [
                'varian'    => 'Varian & Level Kompleksitas',
                'checklist' => 'Checklist & Referensi',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));

        // Style bantu untuk bab Anatomi Visual (badge nomor zona + input mock).
        $badge = 'display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:9999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;line-height:1';
        $mockInput = 'height:36px;padding:8px 12px;border-radius:8px;border:1px solid var(--hairline);background:var(--canvas);color:var(--muted-soft);font-size:13px;display:flex;align-items:center';
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('panduan-dev') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Koding Master</span>
                </div>
                <x-theme-toggle />
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR (per-submenu) ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Sumber: <span class="ds-code">docs/standar-master-module.md</span><br>
                            Acuan kanonik: <span class="ds-code">master-agama</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.koding-master.koding-master-dasar')

                    @include('pages.panduan-dev.koding-master.koding-master-list-form')

                    @include('pages.panduan-dev.koding-master.koding-master-komponen')

                    @include('pages.panduan-dev.koding-master.koding-master-anatomi')

                    @include('pages.panduan-dev.koding-master.koding-master-aturan')

                    @include('pages.panduan-dev.koding-master.koding-master-checklist')

                    {{-- ============ PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6" style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">
                            ← <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])">
                            <span x-text="labels[order[idx() + 1]]"></span> →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
