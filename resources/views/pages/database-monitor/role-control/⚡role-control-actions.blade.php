<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use App\Services\AppMenu;
use App\Support\AksesHalaman;
use App\Support\AksiRole;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public ?int $roleId = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $formRole = [
        'name' => '',
        'guard_name' => 'web',
    ];

    /** ID permission bercentang — string, karena dipakai x-model checkbox (nilai DOM selalu string). */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('roleControl.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'role-control-actions');
        $this->dispatch('focus-role-name');
    }

    #[On('roleControl.openEdit')]
    public function openEdit(?int $roleId = null): void
    {
        if ($roleId === null) {
            $this->dispatch('toast', type: 'error', message: 'ID role tidak valid.');
            return;
        }

        $this->resetForm();
        $this->formMode = 'edit';
        $this->resetValidation();

        $role = DB::table('roles')->where('id', $roleId)->first();
        if (!$role) {
            $this->dispatch('toast', type: 'error', message: 'Role tidak ditemukan.');
            return;
        }

        $this->roleId = (int) $role->id;
        $this->formRole = [
            'name' => $role->name ?? '',
            'guard_name' => $role->guard_name ?? 'web',
        ];

        $this->selectedPermissions = DB::table('role_has_permissions')
            ->where('role_id', $this->roleId)
            ->pluck('permission_id')
            ->map(fn($v) => (string) $v)
            ->all();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'role-control-actions');
        $this->dispatch('focus-role-name');
    }

    #[On('roleControl.requestDelete')]
    public function deleteFromGrid(int $roleId): void
    {
        try {
            $isUsed = DB::table('model_has_roles')->where('role_id', $roleId)->exists();

            if ($isUsed) {
                $this->dispatch('toast', type: 'error', message: 'Role masih dipakai user. Lepas role dari user terlebih dahulu.');
                return;
            }

            DB::transaction(function () use ($roleId) {
                DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
                DB::table('roles')->where('id', $roleId)->delete();
            });

            $this->dispatch('toast', type: 'success', message: 'Role berhasil dihapus.');
            $this->dispatch('refresh-after-role-control.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Role tidak bisa dihapus karena masih dipakai di data lain.');
                return;
            }
            throw $e;
        }
    }

    #[Computed]
    public function allPermissions()
    {
        return DB::table('permissions')
            ->where('guard_name', $this->formRole['guard_name'] ?? 'web')
            ->orderBy('name')
            ->get();
    }

    /**
     * Matriks centang: halaman (dari AppMenu, per grup) × lihat/tambah/ubah/hapus,
     * aksi khusus (AksiRole::KHUSUS, per area), dan sisa permission di luar katalog.
     * Nilai sel = id permission (string), null bila belum dibuat `akses:sinkron`,
     * atau false bila aksi itu tidak berlaku di halaman tsb.
     */
    #[Computed]
    public function matriks(): array
    {
        $idPerNama = $this->allPermissions->pluck('id', 'name')->map(fn ($v) => (string) $v)->all();
        $terpakai = [];

        $halaman = [];
        $sudah = [];
        $menu = collect(AppMenu::all())->sortBy([['groupOrder', 'asc'], ['order', 'asc']]);
        foreach ($menu as $m) {
            if (isset($sudah[$m['route']])) {
                continue; // satu route bisa beberapa entri menu (mis. Casemix ber-tab)
            }
            $sudah[$m['route']] = true;

            // false = aksi tak berlaku di halaman ini ('aksi' AppMenu); null = belum disinkron.
            $sel = [];
            $berlaku = AksesHalaman::aksiBerlaku($m['route']);
            foreach (AksesHalaman::AKSI as $aksi) {
                $nama = AksesHalaman::namaPermission($m['route'], $aksi);
                $sel[$aksi] = in_array($aksi, $berlaku, true) ? ($idPerNama[$nama] ?? null) : false;
                $terpakai[$nama] = true;
            }
            $halaman[$m['group']][] = [
                'route' => $m['route'],
                'title' => $m['title'],
                'terbuka' => in_array(AppMenu::SEMUA_ROLE, $m['roles'], true),
                'sel' => $sel,
                // Tombol khusus halaman ('khusus' AppMenu) → kolom Khusus baris ini.
                'khusus' => collect(AksesHalaman::khususHalaman($m['route']))
                    ->map(function ($k, $kunci) use ($m, $idPerNama, &$terpakai) {
                        $nama = AksesHalaman::namaPermission($m['route'], $kunci);
                        $terpakai[$nama] = true;
                        return ['nama' => $nama, 'label' => $k['label'], 'id' => $idPerNama[$nama] ?? null];
                    })->values()->all(),
            ];
        }

        $khusus = [];
        foreach (AksiRole::KHUSUS as $nama => $a) {
            $khusus[$a['area']][] = ['nama' => $nama, 'label' => $a['label'], 'id' => $idPerNama[$nama] ?? null];
            $terpakai[$nama] = true;
        }

        $lainnya = $this->allPermissions
            ->reject(fn ($p) => isset($terpakai[$p->name]))
            ->map(fn ($p) => ['id' => (string) $p->id, 'nama' => $p->name])
            ->values()
            ->all();

        $belumSinkron = collect($halaman)->flatten(1)->contains(fn ($r) => !$r['terbuka'] && (in_array(null, $r['sel'], true) || in_array(null, array_column($r['khusus'], 'id'), true)))
            || collect($khusus)->flatten(1)->contains(fn ($r) => $r['id'] === null);

        return compact('halaman', 'khusus', 'lainnya', 'belumSinkron');
    }

    protected function rules(): array
    {
        $rules = [
            'formRole.name' => 'required|string|max:125',
            'formRole.guard_name' => 'required|string|max:125',
            'selectedPermissions' => 'array',
            // Tanpa `exists`: itu 1 query per centang (ratusan). Id asing tersaring whereIn di save().
            'selectedPermissions.*' => 'integer',
        ];

        if ($this->formMode === 'create') {
            $rules['formRole.name'] = 'required|string|max:125|unique:roles,name,NULL,id,guard_name,' . ($this->formRole['guard_name'] ?? 'web');
        } else {
            $rules['formRole.name'] = 'required|string|max:125|unique:roles,name,' . $this->roleId . ',id,guard_name,' . ($this->formRole['guard_name'] ?? 'web');
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'unique' => ':attribute sudah digunakan pada guard yang sama.',
            'max' => ':attribute maksimal :max karakter.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'formRole.name' => 'Nama Role',
            'formRole.guard_name' => 'Guard Name',
            'selectedPermissions' => 'Permissions',
        ];
    }

    public function save(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                if ($this->formMode === 'create') {
                    $role = Role::create([
                        'name' => $this->formRole['name'],
                        'guard_name' => $this->formRole['guard_name'],
                    ]);
                    $this->roleId = $role->id;
                } else {
                    $role = Role::findById($this->roleId, $this->formRole['guard_name']);
                    $role->update([
                        'name' => $this->formRole['name'],
                        'guard_name' => $this->formRole['guard_name'],
                    ]);
                }

                $permissions = Permission::whereIn('id', array_map('intval', $this->selectedPermissions))
                    ->where('guard_name', $this->formRole['guard_name'])
                    ->get();

                $role->syncPermissions($permissions);
            });

            $this->dispatch('toast', type: 'success', message: $this->formMode === 'create' ? 'Role berhasil ditambahkan.' : 'Role berhasil diperbarui.');

            $this->closeModal();
            $this->dispatch('refresh-after-role-control.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal simpan: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'role-control-actions');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->formMode = 'create';
        $this->roleId = null;
        $this->formRole = [
            'name' => '',
            'guard_name' => 'web',
        ];
        $this->selectedPermissions = [];
    }
};
?>

<div>
    <x-modal name="role-control-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $roleId ?? 'new']) }}">

            {{-- HEADER --}}
            <x-modul-dokumen.header :judul="$formMode === 'edit' ? 'Ubah Role' : 'Tambah Role Baru'"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                Centang hak akses role: halaman yang bisa dibuka, aksi di dalamnya, dan aksi khusus.
            <x-slot:badge>
                <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                    {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                </x-badge>
            </x-slot:badge>
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20" x-data
                x-on:focus-role-name.window="$nextTick(() => setTimeout(() => $refs.inputRoleName?.focus(), 150))">
                <div class="max-w-full mx-auto p-1">
                    <div class="space-y-4">

                        {{-- DATA ROLE --}}
                        <x-border-form title="Data Role">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div class="md:col-span-2">
                                    <x-input-label value="Nama Role *" class="mb-1" />
                                    <x-text-input wire:model.live="formRole.name" x-ref="inputRoleName"
                                        :error="$errors->has('formRole.name')" class="w-full mt-1"
                                        placeholder="Contoh: Admin, Dokter, Perawat"
                                        x-on:keydown.enter.prevent="$refs.inputGuardName?.focus()" />
                                    <x-input-error :messages="$errors->get('formRole.name')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Guard Name *" class="mb-1" />
                                    <x-select-input wire:model.live="formRole.guard_name" x-ref="inputGuardName"
                                        :error="$errors->has('formRole.guard_name')" class="w-full mt-1">
                                        <option value="web">web</option>
                                        <option value="api">api</option>
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('formRole.guard_name')" class="mt-1" />
                                </div>
                            </div>
                        </x-border-form>

                        {{-- MATRIKS HAK AKSES --}}
                        @php $matriks = $this->matriks; @endphp
                        <x-border-form title="Hak Akses">
                            <div x-data="{
                                    pilih: $wire.entangle('selectedPermissions'),
                                    cari: '',
                                    cocok(teks) { return this.cari === '' || teks.includes(this.cari.toLowerCase()) },
                                    semuaNyala(ids) { return ids.length > 0 && ids.every(i => this.pilih.includes(i)) },
                                    atur(ids) {
                                        const nyala = !this.semuaNyala(ids);
                                        const sisa = this.pilih.filter(i => !ids.includes(i));
                                        this.pilih = nyala ? [...sisa, ...ids] : sisa;
                                    },
                                }" class="space-y-4">

                                <div class="p-3 text-sm text-blue-800 border border-blue-200 rounded-lg bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800/40 dark:text-blue-200">
                                    <p><b>Lihat</b> = menu tampil & halaman bisa dibuka. <b>Tambah / Ubah / Hapus</b> = tombol aksi di halaman itu.
                                        <b>Aksi Khusus</b> = hak di luar keempatnya (buka kunci, kirim bridging, dll.).</p>
                                    <p class="mt-1">User mendapat gabungan hak dari semua role yang dipegangnya. Perubahan berlaku setelah <b>Simpan</b>.
                                        Tanda <b>·</b> = aksi tidak berlaku di halaman itu.</p>
                                </div>

                                @if ($matriks['belumSinkron'])
                                    <div class="flex items-start gap-2 p-3 text-sm border rounded-lg text-amber-800 bg-amber-50 border-amber-200 dark:bg-amber-900/20 dark:border-amber-800/40 dark:text-amber-200">
                                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                        <p>Sebagian hak belum dibuat (tanda —). Jalankan <code class="font-mono">php artisan akses:sinkron</code> di server.</p>
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="w-full sm:w-80">
                                        <x-text-input x-model.debounce.200ms="cari" placeholder="Cari menu / aksi..." class="w-full" />
                                    </div>
                                    <p class="text-sm text-muted dark:text-gray-400">
                                        <span x-text="pilih.length"></span> hak aktif
                                    </p>
                                </div>

                                {{-- Halaman per grup menu --}}
                                <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                                    <table class="w-full text-sm">
                                        <thead class="text-xs uppercase bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Halaman</th>
                                                @foreach (AksesHalaman::AKSI as $aksi)
                                                    <th class="w-20 px-2 py-2 text-center">{{ ucfirst($aksi) }}</th>
                                                @endforeach
                                                <th class="px-2 py-2 text-left">Khusus</th>
                                                <th class="w-16 px-2 py-2 text-center">Baris</th>
                                            </tr>
                                        </thead>
                                        @foreach ($matriks['halaman'] as $grup => $baris)
                                            @php
                                                $teksGrup = mb_strtolower($grup . ' ' . collect($baris)->map(fn ($r) => $r['title'] . ' ' . $r['route'] . ' ' . implode(' ', array_column($r['khusus'], 'label')))->implode(' '));
                                            @endphp
                                            <tbody wire:key="grup-{{ md5($grup) }}" x-show="cocok(@js($teksGrup))">
                                                <tr class="border-t bg-brand-green/5 border-hairline dark:bg-gray-800/60 dark:border-gray-700">
                                                    <td class="px-3 py-2 font-semibold text-ink dark:text-gray-200">{{ $grup }}</td>
                                                    @foreach (AksesHalaman::AKSI as $aksi)
                                                        @php $ids = collect($baris)->pluck('sel.' . $aksi)->filter()->values()->all(); @endphp
                                                        <td class="px-2 py-2 text-center">
                                                            @if ($ids)
                                                                <button type="button" x-on:click="atur(@js($ids))"
                                                                    class="text-xs font-medium hover:underline"
                                                                    x-bind:class="semuaNyala(@js($ids)) ? 'text-brand-green dark:text-brand-lime' : 'text-muted'"
                                                                    title="Centang / lepas semua {{ $aksi }} di grup ini">semua</button>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td></td>
                                                    <td></td>
                                                </tr>
                                                @foreach ($baris as $r)
                                                    @php $idsBaris = array_values(array_filter([...array_values($r['sel']), ...array_column($r['khusus'], 'id')])); @endphp
                                                    <tr wire:key="hal-{{ $r['route'] }}"
                                                        x-show="cocok(@js(mb_strtolower($grup . ' ' . $r['title'] . ' ' . $r['route'] . ' ' . implode(' ', array_column($r['khusus'], 'label')))))"
                                                        class="border-t border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                                        <td class="px-3 py-1.5">
                                                            <div class="text-ink dark:text-gray-200">{{ $r['title'] }}</div>
                                                            <div class="font-mono text-xs text-muted-soft">{{ $r['route'] }}</div>
                                                        </td>
                                                        @if ($r['terbuka'])
                                                            <td colspan="6" class="px-2 py-1.5 text-center">
                                                                <x-badge variant="alternative">Terbuka untuk semua user</x-badge>
                                                            </td>
                                                        @else
                                                            @foreach (AksesHalaman::AKSI as $aksi)
                                                                <td class="px-2 py-1.5 text-center">
                                                                    @if ($r['sel'][$aksi])
                                                                        <input type="checkbox" x-model="pilih" value="{{ $r['sel'][$aksi] }}" aria-label="{{ ucfirst($aksi) }} {{ $r['title'] }}"
                                                                            class="w-4 h-4 bg-gray-100 border-gray-300 rounded cursor-pointer text-brand-green focus:ring-brand-green/50 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                                                    @elseif ($r['sel'][$aksi] === false)
                                                                        <span class="text-muted-soft" title="Tidak berlaku di halaman ini">·</span>
                                                                    @else
                                                                        <span class="text-muted-soft" title="Belum dibuat — jalankan akses:sinkron">—</span>
                                                                    @endif
                                                                </td>
                                                            @endforeach
                                                            <td class="px-2 py-1.5">
                                                                <div class="flex flex-col gap-1">
                                                                    @foreach ($r['khusus'] as $k)
                                                                        <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer text-ink dark:text-gray-300" title="{{ $k['nama'] }}">
                                                                            @if ($k['id'])
                                                                                <input type="checkbox" x-model="pilih" value="{{ $k['id'] }}"
                                                                                    class="w-4 h-4 bg-gray-100 border-gray-300 rounded cursor-pointer text-brand-green focus:ring-brand-green/50 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                                                            @else
                                                                                <span class="text-muted-soft" title="Belum dibuat — jalankan akses:sinkron">—</span>
                                                                            @endif
                                                                            {{ $k['label'] }}
                                                                        </label>
                                                                    @endforeach
                                                                </div>
                                                            </td>
                                                            <td class="px-2 py-1.5 text-center">
                                                                @if ($idsBaris)
                                                                    <button type="button" x-on:click="atur(@js($idsBaris))"
                                                                        class="text-xs font-medium text-muted hover:underline">semua</button>
                                                                @endif
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        @endforeach
                                    </table>
                                </div>

                                {{-- Aksi khusus per area --}}
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($matriks['khusus'] as $area => $baris)
                                        @php
                                            $idsArea = collect($baris)->pluck('id')->filter()->values()->all();
                                            $teksArea = mb_strtolower('aksi khusus ' . $area . ' ' . collect($baris)->map(fn ($r) => $r['label'] . ' ' . $r['nama'])->implode(' '));
                                        @endphp
                                        <div wire:key="area-{{ md5($area) }}" x-show="cocok(@js($teksArea))"
                                            class="border rounded-lg border-hairline dark:border-gray-700">
                                            <div class="flex items-center justify-between px-3 py-2 border-b bg-brand-green/5 border-hairline dark:bg-gray-800/60 dark:border-gray-700">
                                                <span class="text-sm font-semibold text-ink dark:text-gray-200">Aksi Khusus — {{ $area }}</span>
                                                @if ($idsArea)
                                                    <button type="button" x-on:click="atur(@js($idsArea))"
                                                        class="text-xs font-medium hover:underline"
                                                        x-bind:class="semuaNyala(@js($idsArea)) ? 'text-brand-green dark:text-brand-lime' : 'text-muted'">semua</button>
                                                @endif
                                            </div>
                                            <div class="p-3 space-y-2">
                                                @foreach ($baris as $r)
                                                    <div wire:key="khusus-{{ $r['nama'] }}" class="flex items-start gap-2">
                                                        @if ($r['id'])
                                                            <x-check-box x-model="pilih" :value="$r['id']" :id="'khusus-' . md5($r['nama'])" class="pt-0.5" />
                                                        @else
                                                            <span class="w-4 text-muted-soft">—</span>
                                                        @endif
                                                        <label for="khusus-{{ md5($r['nama']) }}" class="cursor-pointer">
                                                            <span class="text-sm text-ink dark:text-gray-200">{{ $r['label'] }}</span>
                                                            <span class="block font-mono text-xs text-muted-soft">{{ $r['nama'] }}</span>
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- Permission di luar katalog (lama / buatan manual) --}}
                                @if ($matriks['lainnya'])
                                    <div class="border rounded-lg border-hairline dark:border-gray-700" x-show="cocok('lainnya ' + @js(mb_strtolower(collect($matriks['lainnya'])->pluck('nama')->implode(' '))))">
                                        <div class="px-3 py-2 text-sm font-semibold border-b text-ink bg-surface-soft border-hairline dark:bg-gray-800/60 dark:border-gray-700 dark:text-gray-200">
                                            Lainnya (di luar katalog menu & aksi khusus)
                                        </div>
                                        <div class="flex flex-wrap gap-4 p-3">
                                            @foreach ($matriks['lainnya'] as $r)
                                                <x-check-box wire:key="lain-{{ $r['id'] }}" x-model="pilih" :value="$r['id']" :label="$r['nama']" />
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <x-input-error :messages="$errors->get('selectedPermissions')" class="mt-1" />
                                <x-input-error :messages="$errors->get('selectedPermissions.*')" class="mt-1" />
                            </div>
                        </x-border-form>

                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-xs text-muted dark:text-gray-400">
                        <span class="hidden sm:inline">Tekan </span>
                        <kbd
                            class="px-1.5 py-0.5 text-xs font-semibold bg-surface-soft border border-gray-300 rounded dark:bg-gray-800 dark:border-gray-600">Enter</kbd>
                        <span class="mx-0.5">untuk berpindah field</span>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" wire:click="closeModal"><x-ikon nama="batal" /> Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled"
                            wire:target="save">
                            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2"><x-ikon nama="simpan" /> {{ $formMode === 'edit' ? 'Perbarui' : 'Simpan' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
