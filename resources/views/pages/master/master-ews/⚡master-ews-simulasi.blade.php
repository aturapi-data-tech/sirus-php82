<?php
// resources/views/pages/master/master-ews/master-ews-simulasi.blade.php
//
// Simulasi skor EWS: isi nilai parameter varian terpilih → lihat skor per
// parameter, total, dan respon yang akan dipilih — memakai master DB terkini
// (EwsMaster::muat) dan mesin yang sama dengan Observasi Lanjutan (EwsSkor).
// Tidak menulis apa pun. Dipakai untuk menguji ambang setelah diubah.

use App\Support\Ews\EwsDefault;
use App\Support\Ews\EwsMaster;
use App\Support\Ews\EwsSkor;
use App\Http\Traits\Concerns\WithRenderVersioningTrait;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    use WithRenderVersioningTrait;

    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public string $varian = 'DEWASA';
    public string $umurBulan = '';
    public array  $nilai = [];
    public array  $paramList = [];   // parameter yang diskor, urut, untuk membangun form
    public ?array $hasil = null;

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    #[On('master.ews.openSimulasi')]
    public function open(string $varian = 'DEWASA'): void
    {
        $this->varian = array_key_exists($varian, EwsDefault::VARIAN) ? $varian : 'DEWASA';
        $this->umurBulan = '';
        $this->hasil = null;
        $this->muatParam();

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-ews-simulasi');
    }

    public function updatedVarian(): void
    {
        $this->hasil = null;
        $this->muatParam();
    }

    private function muatParam(): void
    {
        $master = EwsMaster::muat();
        $this->paramList = [];
        $this->nilai = [];
        foreach (EwsMaster::paramsDiskor($master, $this->varian) as $kode => $param) {
            $this->paramList[] = [
                'kode'    => $kode,
                'desc'    => $param['param_desc'],
                'tipe'    => $param['tipe'],
                'satuan'  => $param['satuan'],
                'wajib'   => $param['wajib'],
                'pilihan' => $param['tipe'] === 'PILIHAN' ? EwsMaster::pilihan($master, $this->varian, $kode) : [],
            ];
            $this->nilai[$kode] = '';
        }
    }

    public function hitung(): void
    {
        $umur = $this->umurBulan === '' ? null : (int) $this->umurBulan;
        $this->hasil = EwsSkor::hitung($this->varian, $this->nilai, EwsMaster::muat(), $umur);
    }

    public function closeModal(): void
    {
        $this->hasil = null;
        $this->dispatch('close-modal', name: 'master-ews-simulasi');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="master-ews-simulasi" size="4xl" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]" wire:key="{{ $this->renderKey('modal', [$varian]) }}">

            {{-- HEADER --}}
            <x-modul-dokumen.header judul="Simulasi Skor EWS"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                Uji ambang master: isi nilai, tekan Hitung. Memakai mesin & master yang sama dengan Observasi Lanjutan.
            </x-modul-dokumen.header>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 space-y-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain>
                <x-border-form title="Nilai parameter">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <x-input-label value="Varian" />
                            <x-select-input wire:model.live="varian" class="w-full mt-1">
                                @foreach (EwsDefault::VARIAN as $kode => $label)
                                    <option value="{{ $kode }}">{{ $label }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Umur pasien (bulan)" />
                            <x-text-input wire:model="umurBulan" type="number" min="0" placeholder="untuk acuan per usia" class="w-full mt-1" />
                        </div>
                    </div>

                    @if ($paramList === [])
                        <p class="mt-4 text-sm text-warning-deep">Master untuk varian ini kosong — jalankan DDL lalu <code>php artisan ews:seed</code>.</p>
                    @else
                        <div class="grid grid-cols-1 gap-3 mt-4 sm:grid-cols-3">
                            @foreach ($paramList as $param)
                                <div wire:key="sim-{{ $varian }}-{{ $param['kode'] }}">
                                    <x-input-label>
                                        {{ $param['desc'] }}
                                        @if ($param['wajib'] !== '1')
                                            <span class="text-xs text-muted-soft">(opsional)</span>
                                        @endif
                                    </x-input-label>
                                    @if ($param['tipe'] === 'PILIHAN')
                                        <x-select-input wire:model="nilai.{{ $param['kode'] }}" class="w-full mt-1">
                                            <option value="">— pilih —</option>
                                            @foreach ($param['pilihan'] as $kode => $label)
                                                <option value="{{ $kode }}">{{ $label }}</option>
                                            @endforeach
                                        </x-select-input>
                                    @else
                                        <div class="relative mt-1">
                                            <x-text-input wire:model="nilai.{{ $param['kode'] }}" type="number" step="0.1" class="w-full pr-14" />
                                            @if ($param['satuan'])
                                                <span class="absolute inset-y-0 right-2 flex items-center text-xs text-muted-soft pointer-events-none">{{ $param['satuan'] }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">
                            <x-primary-button type="button" wire:click="hitung" wire:loading.attr="disabled">Hitung</x-primary-button>
                        </div>
                    @endif
                </x-border-form>

                @if ($hasil !== null)
                    <x-border-form title="Hasil">
                        @if (!$hasil['tersedia'])
                            <p class="text-sm text-warning-deep">Master varian ini tidak tersedia.</p>
                        @else
                            <div class="flex flex-wrap items-center gap-3 mb-4">
                                <span class="inline-flex items-center px-3 py-1 rounded-lg text-lg font-bold {{ EwsSkor::warnaKelas($hasil['warna']) }}">
                                    Total {{ $hasil['total'] }}
                                </span>
                                @if ($hasil['kategori'])
                                    <x-badge variant="gray">{{ $hasil['kategori'] }}</x-badge>
                                    <span class="text-sm text-body dark:text-gray-300">{{ $hasil['frekuensi'] }}</span>
                                @endif
                                @if ($hasil['adaMerah'])
                                    <x-badge variant="danger">ada parameter merah</x-badge>
                                @endif
                                @if (!$hasil['lengkap'])
                                    <x-badge variant="warning">belum lengkap: {{ implode(', ', $hasil['kurang']) }}</x-badge>
                                @endif
                            </div>
                            @if ($hasil['respon'])
                                <p class="mb-4 text-sm text-body dark:text-gray-300">{{ $hasil['respon'] }}</p>
                            @endif
                            <table class="w-full text-sm">
                                <thead class="text-xs font-semibold text-muted uppercase bg-surface-soft dark:bg-gray-800/50 dark:text-gray-400">
                                    <tr>
                                        <th class="px-2 py-2 text-left">Parameter</th>
                                        <th class="px-2 py-2 text-left">Nilai</th>
                                        <th class="px-2 py-2 text-left">Rentang / pilihan</th>
                                        <th class="px-2 py-2 text-center w-16">Skor</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                                    @foreach ($hasil['per'] as $kode => $baris)
                                        <tr wire:key="hasil-{{ $kode }}">
                                            <td class="px-2 py-1.5">{{ $baris['desc'] }}</td>
                                            <td class="px-2 py-1.5 font-mono">{{ $baris['nilai'] === '' || $baris['nilai'] === null ? '-' : $baris['nilai'] }}</td>
                                            <td class="px-2 py-1.5 text-muted">{{ $baris['label'] ?? '-' }}</td>
                                            <td class="px-2 py-1.5 text-center">
                                                <span class="inline-flex items-center justify-center w-8 h-7 rounded font-semibold {{ EwsSkor::skorKelas($baris['skor']) }}">{{ $baris['skor'] ?? '-' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </x-border-form>
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
