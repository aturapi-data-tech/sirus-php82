<?php
// resources/views/pages/master/master-ews/master-ews.blade.php
//
// LIST master EWS (Early Warning System). Dua tampilan dalam satu halaman:
//   parameter → RSMST_EWS_PARAMS (+ jumlah rentang skornya)
//   respon    → RSMST_EWS_RESPONS (interpretasi total skor)
// keduanya disaring per VARIAN (DEWASA / ANAK / NEONATUS / MEOWS).
// Semua tulis DB ada di -actions / -respon-actions; simulasi skor di -simulasi.

use App\Support\Ews\EwsDefault;
use App\Support\Ews\EwsSkor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;
    public string $varian        = 'DEWASA';
    public string $tampilan      = 'parameter'; // parameter | respon

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function setVarian(string $varian): void
    {
        if (!array_key_exists($varian, EwsDefault::VARIAN)) {
            return;
        }
        $this->varian = $varian;
        $this->resetPage();
    }

    public function setTampilan(string $tampilan): void
    {
        if (!in_array($tampilan, ['parameter', 'respon'], true)) {
            return;
        }
        $this->tampilan = $tampilan;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    // ── Parameter ──
    public function openCreate(): void            { $this->dispatch('master.ews.openCreate', varian: $this->varian); }
    public function openEdit(int $paramId): void  { $this->dispatch('master.ews.openEdit', paramId: $paramId); }
    public function requestDelete(int $paramId): void { $this->dispatch('master.ews.requestDelete', paramId: $paramId); }
    public function toggleActive(int $paramId): void { $this->dispatch('master.ews.toggleActive', paramId: $paramId); }

    // ── Respon ──
    public function openCreateRespon(): void           { $this->dispatch('master.ews.openCreateRespon', varian: $this->varian); }
    public function openEditRespon(int $responId): void { $this->dispatch('master.ews.openEditRespon', responId: $responId); }
    public function requestDeleteRespon(int $responId): void { $this->dispatch('master.ews.requestDeleteRespon', responId: $responId); }

    public function openSimulasi(): void { $this->dispatch('master.ews.openSimulasi', varian: $this->varian); }

    #[On('master.ews.saved')]
    public function refreshAfterSaved(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        return $this->tampilan === 'respon' ? $this->rowsRespon() : $this->rowsParameter();
    }

    private function rowsParameter()
    {
        $q = DB::table('rsmst_ews_params as p')
            ->select('p.param_id', 'p.varian', 'p.param_kode', 'p.param_desc', 'p.tipe', 'p.satuan', 'p.urutan', 'p.wajib', 'p.gantikan_kode', 'p.active_status')
            ->selectRaw('(select count(*) from rsmst_ews_rentangs r where r.param_id = p.param_id) as jumlah_rentang')
            ->where('p.varian', $this->varian)
            ->orderBy('p.urutan')->orderBy('p.param_id');

        if (trim($this->searchKeyword) !== '') {
            $keyword = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($subQuery) use ($keyword) {
                $subQuery->whereRaw('UPPER(p.param_kode) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(p.param_desc) LIKE ?', ["%{$keyword}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }

    private function rowsRespon()
    {
        $q = DB::table('rsmst_ews_respons')
            ->select('respon_id', 'varian', 'urutan', 'skor_min', 'skor_max', 'param_merah', 'kategori', 'warna', 'frekuensi', 'frekuensi_menit', 'respon')
            ->where('varian', $this->varian)
            ->orderBy('urutan')->orderBy('respon_id');

        if (trim($this->searchKeyword) !== '') {
            $keyword = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($subQuery) use ($keyword) {
                $subQuery->whereRaw('UPPER(kategori) LIKE ?', ["%{$keyword}%"])
                    ->orWhereRaw('UPPER(respon) LIKE ?', ["%{$keyword}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }

    public function varianList(): array
    {
        return EwsDefault::VARIAN;
    }
};
?>

<div>

    <x-page-title
        title="Master EWS"
        subtitle="Parameter, rentang skor & respon Early Warning System per varian (Dewasa / Anak / Neonatus / MEOWS)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3">
                    <x-tabs variant="underline">
                        @foreach ($this->varianList() as $kode => $label)
                            <x-tab :active="$varian === $kode" wire:click="setVarian('{{ $kode }}')">{{ $label }}</x-tab>
                        @endforeach
                    </x-tabs>

                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div>
                                <x-input-label value="Tampilan" class="mb-1 text-xs font-medium text-muted dark:text-gray-400" />
                                <x-tabs variant="pill">
                                    <x-tab :active="$tampilan === 'parameter'" wire:click="setTampilan('parameter')">Parameter & Rentang</x-tab>
                                    <x-tab :active="$tampilan === 'respon'" wire:click="setTampilan('respon')">Respon Skor</x-tab>
                                </x-tabs>
                            </div>
                            <div class="w-full sm:w-72">
                                <x-input-label for="searchKeyword" value="Cari" class="sr-only" />
                                <x-text-input id="searchKeyword" type="text"
                                    wire:model.live.debounce.300ms="searchKeyword"
                                    placeholder="{{ $tampilan === 'respon' ? 'Cari kategori / respon...' : 'Cari kode / nama parameter...' }}"
                                    class="block w-full" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <div class="w-28">
                                <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                                <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="20">20</option>
                                    <option value="100">100</option>
                                </x-select-input>
                            </div>
                            <x-secondary-button type="button" wire:click="openSimulasi">Simulasi Skor</x-secondary-button>
                            @if ($tampilan === 'respon')
                                <x-primary-button type="button" wire:click="openCreateRespon">+ Tambah Respon</x-primary-button>
                            @else
                                <x-primary-button type="button" wire:click="openCreate">+ Tambah Parameter</x-primary-button>
                            @endif
                            <x-toolbar-refresh-reset :label="null" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    @if ($tampilan === 'respon')
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th class="ds-c">Urutan</th>
                                    <th>Total Skor</th>
                                    <th>Kategori</th>
                                    <th>Warna</th>
                                    <th>Frekuensi Pantau</th>
                                    <th>Respon Klinis</th>
                                    <th class="ds-c">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->rows as $row)
                                    <tr wire:key="ews-respon-{{ $row->respon_id }}">
                                        <td class="ds-c ds-td-token">{{ $row->urutan }}</td>
                                        <td class="ds-td-strong whitespace-nowrap">
                                            @if ($row->skor_min !== null || $row->skor_max !== null)
                                                {{ EwsDefault::labelRentang($row->skor_min, $row->skor_max) }}
                                            @endif
                                            @if ($row->param_merah === '1')
                                                <x-badge variant="danger" class="ml-1">+ 1 parameter merah</x-badge>
                                            @endif
                                        </td>
                                        <td>{{ $row->kategori }}</td>
                                        <td>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ EwsSkor::warnaKelas($row->warna) }}">{{ $row->warna }}</span>
                                        </td>
                                        <td class="whitespace-nowrap">{{ $row->frekuensi }}
                                            @if ($row->frekuensi_menit !== null)
                                                <span class="text-xs text-muted-soft">({{ $row->frekuensi_menit }} mnt)</span>
                                            @endif
                                        </td>
                                        <td class="max-w-md"><div class="line-clamp-2 text-sm" title="{{ $row->respon }}">{{ $row->respon }}</div></td>
                                        <td class="ds-c">
                                            <div class="flex justify-center gap-2">
                                                <x-action-edit wire:click="openEditRespon({{ $row->respon_id }})" />
                                                <x-action-delete :action="'requestDeleteRespon(' . $row->respon_id . ')'"
                                                    title="Hapus Respon EWS" message="Yakin hapus respon {{ $row->kategori }} ({{ $row->frekuensi }})?" />
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-10">
                                            <div class="flex flex-col items-center justify-center gap-3">
                                                <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada respon skor untuk varian ini.</p>
                                                <p class="text-sm text-muted-soft">Isi awal: <code>php artisan ews:seed</code> setelah DDL <code>docs/ddl-ews.sql</code> dijalankan.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10">
                                <tr>
                                    <th class="ds-c">Urutan</th>
                                    <th>Kode JSON</th>
                                    <th>Parameter</th>
                                    <th>Tipe</th>
                                    <th>Satuan</th>
                                    <th class="ds-c">Wajib</th>
                                    <th class="ds-c">Rentang</th>
                                    <th>Menggantikan</th>
                                    <th class="ds-c">Aktif</th>
                                    <th class="ds-c">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->rows as $row)
                                    <tr wire:key="ews-param-{{ $row->param_id }}">
                                        <td class="ds-c ds-td-token">{{ $row->urutan }}</td>
                                        <td class="ds-td-token">{{ $row->param_kode }}</td>
                                        <td class="ds-td-strong">{{ $row->param_desc }}</td>
                                        <td>
                                            <x-badge :variant="$row->tipe === 'PILIHAN' ? 'info' : ($row->tipe === 'REFERENSI' ? 'gray' : 'brand')">{{ $row->tipe }}</x-badge>
                                        </td>
                                        <td>{{ $row->satuan ?: '-' }}</td>
                                        <td class="ds-c">{{ $row->wajib === '1' ? 'Ya' : '-' }}</td>
                                        <td class="ds-c">{{ $row->jumlah_rentang }}</td>
                                        <td class="ds-td-token">{{ $row->gantikan_kode ?: '-' }}</td>
                                        <td class="ds-c">
                                            <x-toggle :current="$row->active_status" trueValue="1" falseValue="0"
                                                wireClick="toggleActive({{ $row->param_id }})" />
                                        </td>
                                        <td class="ds-c">
                                            <div class="flex justify-center gap-2">
                                                <x-action-edit wire:click="openEdit({{ $row->param_id }})" />
                                                <x-action-delete :action="'requestDelete(' . $row->param_id . ')'"
                                                    title="Hapus Parameter EWS" message="Yakin hapus parameter {{ $row->param_desc }} beserta rentang skornya?" />
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-6 py-10">
                                            <div class="flex flex-col items-center justify-center gap-3">
                                                <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada parameter untuk varian ini.</p>
                                                <p class="text-sm text-muted-soft">Isi awal: <code>php artisan ews:seed</code> setelah DDL <code>docs/ddl-ews.sql</code> dijalankan.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- PAGINATION --}}
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-ews.master-ews-actions wire:key="master-ews-actions" />
            <livewire:pages::master.master-ews.master-ews-respon-actions wire:key="master-ews-respon-actions" />
            <livewire:pages::master.master-ews.master-ews-simulasi wire:key="master-ews-simulasi" />

        </div>
    </div>
</div>
