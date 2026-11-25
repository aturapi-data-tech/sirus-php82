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
use App\Support\ProgramAkses;
use Illuminate\Support\Facades\Route;

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
     * Matriks centang PER KELOMPOK → PROGRAM (App\Support\ProgramAkses, urutan berkas definisi):
     * tiap program memuat baris halamannya (Lihat/Tambah/Ubah/Hapus + tombol khusus halaman)
     * dan baris aksi khususnya — semua hak satu program tampil berdampingan.
     * Nilai sel = id permission (string), null bila belum dibuat, atau false bila aksi itu
     * tidak berlaku di halaman tsb. 'lainnya' = permission di luar katalog.
     */
    #[Computed]
    public function matriks(): array
    {
        $idPerNama = $this->allPermissions->pluck('id', 'name')->map(fn ($v) => (string) $v)->all();
        $terpakai = [];
        $sudah = [];
        $program = [];

        foreach (ProgramAkses::kelompok() as $kelompok) {
            foreach ($kelompok['program'] as $namaProgram => $definisi) {
                $halaman = [];
                foreach ($definisi['menu'] ?? [] as $m) {
                    // Satu route bisa beberapa entri menu (mis. Casemix ber-tab) → satu baris saja.
                    // Halaman gabungan bertab (Antrian Apotek/Kasir) tak punya centang sendiri → tak tampil.
                    if (!Route::has($m['route']) || isset($sudah[$m['route']]) || isset(AksesHalaman::gabungan()[$m['route']])) {
                        continue;
                    }
                    $sudah[$m['route']] = true;

                    // false = aksi tak berlaku di halaman ini ('aksi' menu); null = belum disinkron.
                    $sel = [];
                    $berlaku = AksesHalaman::aksiBerlaku($m['route']);
                    foreach (AksesHalaman::AKSI as $aksi) {
                        $nama = AksesHalaman::namaPermission($m['route'], $aksi);
                        $sel[$aksi] = in_array($aksi, $berlaku, true) ? ($idPerNama[$nama] ?? null) : false;
                        $terpakai[$nama] = true;
                    }
                    $halaman[] = [
                        'route' => $m['route'],
                        'title' => $m['title'],
                        'terbuka' => in_array(AppMenu::SEMUA_ROLE, $m['roles'], true),
                        'sel' => $sel,
                        // Tombol khusus halaman ('khusus' menu) → kolom Khusus baris ini.
                        'khusus' => collect(AksesHalaman::khususHalaman($m['route']))
                            ->map(function ($k, $kunci) use ($m, $idPerNama, &$terpakai) {
                                $nama = AksesHalaman::namaPermission($m['route'], $kunci);
                                $terpakai[$nama] = true;
                                return ['nama' => $nama, 'label' => $k['label'], 'id' => $idPerNama[$nama] ?? null];
                            })->values()->all(),
                    ];
                }

                $khusus = [];
                foreach ($definisi['aksiKhusus'] ?? [] as $nama => $aksi) {
                    $khusus[] = ['nama' => $nama, 'label' => $aksi['label'], 'id' => $idPerNama[$nama] ?? null];
                    $terpakai[$nama] = true;
                }

                if ($halaman || $khusus) {
                    $program[$kelompok['judul']][$namaProgram] = ['halaman' => $halaman, 'khusus' => $khusus];
                }
            }
        }

        $lainnya = $this->allPermissions
            ->reject(fn ($p) => isset($terpakai[$p->name]))
            ->map(fn ($p) => ['id' => (string) $p->id, 'nama' => $p->name])
            ->values()
            ->all();

        $belumSinkron = false;
        foreach ($program as $daftarProgram) {
            foreach ($daftarProgram as $isi) {
                foreach ($isi['halaman'] as $r) {
                    if (!$r['terbuka'] && (in_array(null, $r['sel'], true) || in_array(null, array_column($r['khusus'], 'id'), true))) {
                        $belumSinkron = true;
                    }
                }
                if (in_array(null, array_column($isi['khusus'], 'id'), true)) {
                    $belumSinkron = true;
                }
            }
        }

        return compact('program', 'lainnya', 'belumSinkron');
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

            {{-- BODY — sengaja TANPA overflow-y-auto: yang scroll adalah panel x-modal (pembungkus
                 di atas min-h, bukan tinggi tetap). overflow di sini menjadikannya wadah sticky yang
                 tak pernah scroll, sehingga thead tabel hak akses tidak bisa menempel. --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft/70 dark:bg-gray-950/20" x-data
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

                                {{-- Halaman per grup menu. Tanpa overflow-x-auto: pembungkus ber-overflow jadi
                                     wadah scroll sendiri dan mematikan thead sticky terhadap scroll modal. --}}
                                <div class="border rounded-lg border-hairline dark:border-gray-700">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 z-10 text-xs uppercase shadow-sm bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Halaman</th>
                                                @foreach (AksesHalaman::AKSI as $aksi)
                                                    <th class="w-20 px-2 py-2 text-center">{{ ucfirst($aksi) }}</th>
                                                @endforeach
                                                <th class="px-2 py-2 text-left">Khusus</th>
                                                <th class="w-16 px-2 py-2 text-center">Baris</th>
                                            </tr>
                                        </thead>
                                        {{-- Per KELOMPOK (Rawat Jalan, UGD, ...) → per PROGRAM: baris judul program
                                             (tombol "semua" per kolom), baris halamannya, lalu baris aksi khususnya. --}}
                                        @foreach ($matriks['program'] as $judulKelompok => $daftarProgram)
                                            @php
                                                $teksKelompok = mb_strtolower($judulKelompok . ' ' . collect($daftarProgram)->map(fn ($isi, $namaProgram) => $namaProgram . ' '
                                                    . collect($isi['halaman'])->map(fn ($r) => $r['title'] . ' ' . $r['route'] . ' ' . implode(' ', array_column($r['khusus'], 'label')))->implode(' ') . ' '
                                                    . collect($isi['khusus'])->map(fn ($r) => $r['label'] . ' ' . $r['nama'])->implode(' '))->implode(' '));
                                            @endphp
                                            <tbody wire:key="kelompok-{{ md5($judulKelompok) }}" x-show="cocok(@js($teksKelompok))">
                                                <tr class="border-t-2 border-brand-green/40 bg-brand-green/10 dark:bg-gray-700/60 dark:border-gray-600">
                                                    <td colspan="{{ count(AksesHalaman::AKSI) + 3 }}" class="px-3 py-2 text-sm font-bold tracking-wide uppercase text-ink dark:text-gray-100">{{ $judulKelompok }}</td>
                                                </tr>
                                            </tbody>
                                            @foreach ($daftarProgram as $namaProgram => $isi)
                                                @php
                                                    $awalan = mb_strtolower($judulKelompok . ' ' . $namaProgram . ' ');
                                                    $teksProgram = $awalan
                                                        . mb_strtolower(collect($isi['halaman'])->map(fn ($r) => $r['title'] . ' ' . $r['route'] . ' ' . implode(' ', array_column($r['khusus'], 'label')))->implode(' ') . ' '
                                                        . collect($isi['khusus'])->map(fn ($r) => $r['label'] . ' ' . $r['nama'])->implode(' '));
                                                    $idsKhususProgram = array_values(array_filter([
                                                        ...collect($isi['halaman'])->flatMap(fn ($r) => array_column($r['khusus'], 'id'))->all(),
                                                        ...array_column($isi['khusus'], 'id'),
                                                    ]));
                                                    $idsProgram = array_values(array_filter([
                                                        ...collect($isi['halaman'])->flatMap(fn ($r) => array_values($r['sel']))->all(),
                                                        ...$idsKhususProgram,
                                                    ]));
                                                @endphp
                                                <tbody wire:key="program-{{ md5($judulKelompok . '|' . $namaProgram) }}" x-show="cocok(@js($teksProgram))">
                                                    <tr class="border-t bg-brand-green/5 border-hairline dark:bg-gray-800/60 dark:border-gray-700">
                                                        <td class="px-3 py-2 font-semibold text-ink dark:text-gray-200">{{ $namaProgram }}</td>
                                                        @foreach (AksesHalaman::AKSI as $aksi)
                                                            @php $ids = collect($isi['halaman'])->pluck('sel.' . $aksi)->filter()->values()->all(); @endphp
                                                            <td class="px-2 py-2 text-center">
                                                                @if ($ids)
                                                                    <button type="button" x-on:click="atur(@js($ids))"
                                                                        class="text-xs font-medium hover:underline"
                                                                        x-bind:class="semuaNyala(@js($ids)) ? 'text-brand-green dark:text-brand-lime' : 'text-muted'"
                                                                        title="Centang / lepas semua {{ $aksi }} di {{ $namaProgram }}">semua</button>
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                        <td class="px-2 py-2">
                                                            @if ($idsKhususProgram)
                                                                <button type="button" x-on:click="atur(@js($idsKhususProgram))"
                                                                    class="text-xs font-medium hover:underline"
                                                                    x-bind:class="semuaNyala(@js($idsKhususProgram)) ? 'text-brand-green dark:text-brand-lime' : 'text-muted'"
                                                                    title="Centang / lepas semua aksi khusus {{ $namaProgram }}">semua</button>
                                                            @endif
                                                        </td>
                                                        <td class="px-2 py-2 text-center">
                                                            @if ($idsProgram)
                                                                <button type="button" x-on:click="atur(@js($idsProgram))"
                                                                    class="text-xs font-medium text-muted hover:underline"
                                                                    title="Centang / lepas SEMUA hak {{ $namaProgram }}">semua</button>
                                                            @endif
                                                        </td>
                                                    </tr>

                                                    {{-- Halaman program ini --}}
                                                    @foreach ($isi['halaman'] as $r)
                                                        @php $idsBaris = array_values(array_filter([...array_values($r['sel']), ...array_column($r['khusus'], 'id')])); @endphp
                                                        <tr wire:key="hal-{{ $r['route'] }}"
                                                            x-show="cocok(@js($awalan . mb_strtolower($r['title'] . ' ' . $r['route'] . ' ' . implode(' ', array_column($r['khusus'], 'label')))))"
                                                            class="border-t border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                                            <td class="px-3 py-1.5 pl-6">
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
                                                                            <x-toggle-alpine x-model="pilih" :value="$r['sel'][$aksi]" :aria-label="ucfirst($aksi) . ' ' . $r['title']" />
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
                                                                            @if ($k['id'])
                                                                                <x-toggle-alpine x-model="pilih" :value="$k['id']" :label="$k['label']" :title="$k['nama']" />
                                                                            @else
                                                                                <span class="text-sm text-muted-soft" title="Belum dibuat — jalankan akses:sinkron">— {{ $k['label'] }}</span>
                                                                            @endif
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

                                                    {{-- Aksi khusus program ini (tombol di modal/EMR, posting/batal, ...) --}}
                                                    @foreach ($isi['khusus'] as $r)
                                                        <tr wire:key="khusus-{{ $r['nama'] }}"
                                                            x-show="cocok(@js($awalan . mb_strtolower($r['label'] . ' ' . $r['nama'])))"
                                                            class="border-t border-hairline dark:border-gray-700 hover:bg-surface-soft dark:hover:bg-gray-800/40">
                                                            <td class="px-3 py-1.5 pl-6">
                                                                <div class="text-ink dark:text-gray-200">{{ $r['label'] }}</div>
                                                                <div class="font-mono text-xs text-muted-soft">{{ $r['nama'] }}</div>
                                                            </td>
                                                            <td colspan="{{ count(AksesHalaman::AKSI) }}" class="px-2 py-1.5 text-xs text-center text-muted-soft">aksi khusus</td>
                                                            <td class="px-2 py-1.5">
                                                                @if ($r['id'])
                                                                    <x-toggle-alpine x-model="pilih" :value="$r['id']" :aria-label="$r['label']" />
                                                                @else
                                                                    <span class="text-sm text-muted-soft" title="Belum dibuat — jalankan akses:sinkron">—</span>
                                                                @endif
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            @endforeach
                                        @endforeach
                                    </table>
                                </div>

                                {{-- Permission di luar katalog (lama / buatan manual) --}}
                                @if ($matriks['lainnya'])
                                    <div class="border rounded-lg border-hairline dark:border-gray-700" x-show="cocok('lainnya ' + @js(mb_strtolower(collect($matriks['lainnya'])->pluck('nama')->implode(' '))))">
                                        <div class="px-3 py-2 text-sm font-semibold border-b text-ink bg-surface-soft border-hairline dark:bg-gray-800/60 dark:border-gray-700 dark:text-gray-200">
                                            Lainnya (di luar katalog menu & aksi khusus)
                                        </div>
                                        <div class="flex flex-wrap gap-4 p-3">
                                            @foreach ($matriks['lainnya'] as $r)
                                                <x-toggle-alpine wire:key="lain-{{ $r['id'] }}" x-model="pilih" :value="$r['id']" :label="$r['nama']" />
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
