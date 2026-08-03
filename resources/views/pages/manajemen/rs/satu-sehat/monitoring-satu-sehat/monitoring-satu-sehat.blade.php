<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Support\SatuSehatMonitor;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public string $filterBulan = '';        // mm/yyyy
    public string $filterModul = 'RJ';      // RJ | UGD | RI
    public string $searchKeyword = '';
    public bool $filterBelumSaja = false;
    public int $itemsPerPage = 25;

    public function mount(): void
    {
        $this->filterBulan = Carbon::now()->format('m/Y');
    }

    public function updatedFilterBulan(): void      { $this->resetPage(); }
    public function updatedFilterModul(): void      { $this->resetPage(); }
    public function updatedSearchKeyword(): void    { $this->resetPage(); }
    public function updatedFilterBelumSaja(): void  { $this->resetPage(); }
    public function updatedItemsPerPage(): void     { $this->resetPage(); }

    /** 'YYYY-MM' dari filter mm/yyyy. */
    private function bulanIso(): string
    {
        try {
            return Carbon::createFromFormat('m/Y', trim($this->filterBulan))->format('Y-m');
        } catch (\Exception) {
            return Carbon::now()->format('Y-m');
        }
    }

    #[Computed]
    public function rekap(): array
    {
        return SatuSehatMonitor::rekapBulanSemua($this->bulanIso());
    }

    #[Computed]
    public function capaian(): array
    {
        // Penilaian bulan berjalan menilai kiriman bulan LALU (slide Kemenkes).
        return SatuSehatMonitor::capaian(Carbon::now()->format('Y-m'));
    }

    #[Computed]
    public function daftarKunjungan()
    {
        $modul = SatuSehatMonitor::MODUL[$this->filterModul] ?? SatuSehatMonitor::MODUL['RJ'];

        $pilih = [
            "h.{$modul['pk']} as no_kunjungan",
            DB::raw("to_char(h.{$modul['tanggal']},'dd/mm/yyyy hh24:mi') as tgl_display"),
            'h.reg_no',
            'p.reg_name',
        ];
        foreach (array_keys(SatuSehatMonitor::RESOURCE) as $resource) {
            $pilih[] = DB::raw(SatuSehatMonitor::ekspresiTerkirim($this->filterModul, $resource) . " as {$resource}");
        }

        $query = DB::table($modul['tabel'] . ' as h')
            ->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')
            ->select($pilih)
            ->whereRaw("to_char(h.{$modul['tanggal']},'yyyy-mm') = ?", [$this->bulanIso()]);

        if (trim($this->searchKeyword) !== '') {
            $keyword = '%' . strtoupper(trim($this->searchKeyword)) . '%';
            $query->where(function ($subQuery) use ($keyword, $modul) {
                $subQuery->whereRaw('UPPER(p.reg_name) like ?', [$keyword])
                    ->orWhereRaw('UPPER(h.reg_no) like ?', [$keyword])
                    ->orWhereRaw("TO_CHAR(h.{$modul['pk']}) like ?", [$keyword]);
            });
        }

        if ($this->filterBelumSaja) {
            $query->whereRaw(SatuSehatMonitor::ekspresiTerkirim($this->filterModul, 'encounter') . ' = 0');
        }

        return $query->orderByDesc("h.{$modul['tanggal']}")->paginate($this->itemsPerPage);
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterBelumSaja']);
        $this->filterModul = 'RJ';
        $this->filterBulan = Carbon::now()->format('m/Y');
        $this->resetPage();
    }
};
?>

<div>
    <x-page-title title="Monitoring Pengiriman SATUSEHAT"
        subtitle="Kunjungan mana yang sudah & belum terkirim, plus capaian bulanan" />

    <div class="w-full min-h-[calc(100vh-5rem)] bg-canvas dark:bg-gray-800">
        <div class="px-6 py-8 space-y-6">

            {{-- ── CAPAIAN (dashboard) ── --}}
            @php $capaian = $this->capaian; @endphp
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                @foreach ([
                    ['Skor Kelengkapan', $capaian['kelengkapan'], 'bobot 40% — resource wajib yang sudah pernah terkirim tiap modul'],
                    ['Skor Konsistensi', $capaian['konsistensi'], 'bobot 60% — rata-rata growth 6 resource wajib'],
                    ['Capaian', $capaian['capaian'], '(kelengkapan × 40%) + (konsistensi × 60%)'],
                ] as [$judul, $nilai, $keterangan])
                    <div class="p-5 border bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs font-semibold tracking-wide uppercase text-muted dark:text-gray-400">{{ $judul }}</div>
                        <div class="mt-1 text-3xl font-bold text-ink dark:text-white tabular-nums">
                            {{ $nilai === null ? '—' : number_format($nilai, 1) . '%' }}
                        </div>
                        <div class="mt-1 text-xs text-muted dark:text-gray-400">{{ $keterangan }}</div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 text-xs border rounded-xl border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                Menilai kiriman bulan <strong>{{ $capaian['bulanNilai'] }}</strong>, sesuai alur Kemenkes: penilaian bulan berjalan memakai data bulan sebelumnya.
                Rubrik resmi "skor kelengkapan" belum terbit — di sini diartikan <em>persentase resource wajib yang sudah pernah terkirim per modul</em>, supaya angkanya bisa ditelusuri dan dikoreksi saat rubriknya keluar.
            </div>

            {{-- ── KONSISTENSI / GROWTH per resource ── --}}
            @php $konsistensi = $capaian['rincianKonsistensi']; @endphp
            <div class="overflow-hidden border bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-300">
                        Konsistensi 6 Resource Wajib — {{ $konsistensi['bulanSatu'] }} dibanding {{ $konsistensi['bulanDua'] }}
                    </h3>
                    @if ($konsistensi['takTerhitung'] > 0)
                        <span class="text-xs text-amber-600 dark:text-amber-400">{{ $konsistensi['takTerhitung'] }} resource tak terhitung (pembagi 0)</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs tracking-wide uppercase bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left">Resource</th>
                                <th class="px-4 py-3 text-right">{{ $konsistensi['bulanDua'] }}</th>
                                <th class="px-4 py-3 text-right">{{ $konsistensi['bulanSatu'] }}</th>
                                <th class="px-4 py-3 text-right">Growth</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                            @foreach ($konsistensi['growth'] as $kunci => $baris)
                                <tr>
                                    <td class="px-4 py-2 text-ink dark:text-gray-200">
                                        {{ \App\Support\SatuSehatMonitor::RESOURCE[$kunci]['label'] }}
                                        <span class="text-xs text-muted dark:text-gray-500">({{ \App\Support\SatuSehatMonitor::RESOURCE[$kunci]['fhir'] }})</span>
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-muted dark:text-gray-400">{{ number_format($baris['bulanDua']) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-ink dark:text-gray-200">{{ number_format($baris['bulanSatu']) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold tabular-nums {{ $baris['nilai'] === null ? 'text-muted-soft' : ($baris['nilai'] >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400') }}">
                                        {{ $baris['nilai'] === null ? '—' : number_format($baris['nilai'], 1) . '%' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── FILTER ── --}}
            <div class="flex flex-wrap items-end gap-3 p-4 border bg-surface-soft border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-800/40">
                <div>
                    <x-input-label value="Bulan" class="mb-1" />
                    <x-text-input wire:model.live="filterBulan" placeholder="mm/yyyy" class="w-32 text-sm font-mono" />
                </div>
                <div>
                    <x-input-label value="Modul" class="mb-1" />
                    <x-select-input wire:model.live="filterModul" class="w-40 text-sm">
                        @foreach (\App\Support\SatuSehatMonitor::MODUL as $kode => $modul)
                            <option value="{{ $kode }}">{{ $modul['label'] }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div class="flex-1 min-w-[200px]">
                    <x-input-label value="Cari (nama / No. RM / no. kunjungan)" class="mb-1" />
                    <x-text-input wire:model.live.debounce.400ms="searchKeyword" placeholder="Ketik untuk mencari..." class="w-full text-sm" />
                </div>
                <label class="flex items-center gap-2 pb-2 text-sm text-body dark:text-gray-300">
                    <input type="checkbox" wire:model.live="filterBelumSaja" class="rounded border-hairline text-brand-green focus:ring-brand-green">
                    Belum terkirim saja
                </label>
                <x-secondary-button type="button" wire:click="resetFilters">Reset</x-secondary-button>
            </div>

            {{-- ── RINGKASAN BULAN TERPILIH ── --}}
            @php $rekap = $this->rekap; @endphp
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach (\App\Support\SatuSehatMonitor::RESOURCE as $kunci => $meta)
                    @php
                        $terkirim = $rekap['gabungan']['terkirim'][$kunci];
                        $total = max($rekap['gabungan']['total'], 1);
                        $persen = round($terkirim / $total * 100, 1);
                    @endphp
                    <div class="p-3 border bg-canvas border-hairline rounded-xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-xs truncate text-muted dark:text-gray-400" title="{{ $meta['fhir'] }}">{{ $meta['label'] }}</div>
                        <div class="text-lg font-semibold text-ink dark:text-gray-100 tabular-nums">
                            {{ number_format($terkirim) }}<span class="text-xs font-normal text-muted"> / {{ number_format($rekap['gabungan']['total']) }}</span>
                        </div>
                        <div class="text-xs {{ $meta['penanda'] === [] ? 'text-muted-soft' : ($persen > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400') }}">
                            {{ $meta['penanda'] === [] ? 'belum ada pengirimnya' : $persen . '%' }}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ── GRID KUNJUNGAN ── --}}
            <div class="overflow-hidden border bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-3 border-b border-hairline dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-body dark:text-gray-300">
                        Daftar Kunjungan — {{ \App\Support\SatuSehatMonitor::MODUL[$filterModul]['label'] }}
                    </h3>
                    <x-badge variant="gray">{{ number_format($this->daftarKunjungan->total()) }} kunjungan</x-badge>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs tracking-wide uppercase bg-surface-soft text-muted dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-left whitespace-nowrap">Tanggal</th>
                                <th class="px-4 py-3 text-left whitespace-nowrap">No.</th>
                                <th class="px-4 py-3 text-left whitespace-nowrap">Pasien</th>
                                @foreach (\App\Support\SatuSehatMonitor::RESOURCE as $meta)
                                    <th class="px-2 py-3 text-center" title="{{ $meta['fhir'] }}">{{ $meta['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                            @forelse ($this->daftarKunjungan as $row)
                                <tr class="hover:bg-surface-soft/60 dark:hover:bg-gray-800/40">
                                    <td class="px-4 py-2 font-mono text-xs whitespace-nowrap text-muted dark:text-gray-400">{{ $row->tgl_display }}</td>
                                    <td class="px-4 py-2 font-mono text-xs whitespace-nowrap text-ink dark:text-gray-200">{{ $row->no_kunjungan }}</td>
                                    <td class="px-4 py-2 whitespace-nowrap text-ink dark:text-gray-200">
                                        {{ $row->reg_name }}
                                        <span class="block text-xs text-muted dark:text-gray-500">{{ $row->reg_no }}</span>
                                    </td>
                                    @foreach (array_keys(\App\Support\SatuSehatMonitor::RESOURCE) as $kunci)
                                        @php $terkirim = (int) ($row->{strtolower($kunci)} ?? $row->{$kunci} ?? 0); @endphp
                                        <td class="px-2 py-2 text-center">
                                            @if ($terkirim === 1)
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" title="Terkirim">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                </span>
                                            @else
                                                <span class="text-muted-soft dark:text-gray-600">–</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-4 py-10 text-sm text-center text-muted-soft dark:text-gray-600">
                                        Tidak ada kunjungan pada bulan &amp; modul ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 border-t border-hairline dark:border-gray-700">
                    {{ $this->daftarKunjungan->links() }}
                </div>
            </div>

        </div>
    </div>
</div>
