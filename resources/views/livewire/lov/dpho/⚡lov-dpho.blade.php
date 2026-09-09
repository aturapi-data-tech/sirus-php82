<?php

use Livewire\Component;
use Illuminate\Support\Facades\Cache;
use App\Http\Traits\BPJS\ApotekTrait;

/**
 * LOV Daftar Obat DPHO BPJS (Apotek Online).
 *
 * Sumbernya API `referensi/dpho`, BUKAN tabel lokal — karena DPHO adalah daftar
 * berversi milik BPJS yang tak boleh dibekukan ke DB. Endpoint itu mengembalikan
 * SELURUH daftar sekaligus tanpa parameter pencarian, jadi polanya: tarik sekali,
 * cache, lalu saring di memori tiap ketikan.
 *
 * DIRANCANG HIDUP SEBELUM API AKTIF. Selama CID belum diaktifkan BPJS, tarik data
 * gagal dan LOV menampilkan pesan jujur "belum bisa diakses" alih-alih tampak
 * rusak. Begitu CID aktif, komponen ini langsung berfungsi tanpa diubah — itulah
 * inti membuatnya sekarang: fiturnya menunggu di Master Obat, tinggal API-nya
 * yang menyusul.
 */
new class extends Component {
    use ApotekTrait;

    /** Pembeda LOV ini dipakai di form mana. */
    public string $target = 'default';

    public string $label = 'Cari Obat DPHO';
    public string $placeholder = 'Ketik kode/nama obat DPHO...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;

    /** Terpilih: ['kode' => ..., 'nama' => ...]. */
    public ?array $selected = null;

    /** Mode edit: form induk mengirim kode DPHO yang sudah tersimpan. */
    public ?string $initialKode = null;

    public bool $readonly = false;

    /** Pesan bila katalog tak bisa ditarik (mis. CID belum aktif). '' = normal. */
    public string $gangguan = '';

    public function mount(): void
    {
        if (!$this->initialKode) {
            return;
        }

        // Tampilkan kode yang sudah tersimpan. Namanya dicoba dari katalog (kalau
        // API sudah hidup); kalau belum, tampilkan kodenya saja — kolom kita memang
        // hanya menyimpan kode, bukan nama (lihat DDL kode_dpho).
        $nama = '';
        foreach ($this->katalog() as $obat) {
            if ((string) ($obat['kodeobat'] ?? '') === $this->initialKode) {
                $nama = (string) ($obat['namaobat'] ?? '');
                break;
            }
        }

        $this->selected = ['kode' => $this->initialKode, 'nama' => $nama];
    }

    /**
     * Katalog DPHO utuh, di-cache 1 jam supaya tidak ditarik ulang tiap ketikan
     * maupun tiap komponen. Return [] bila gagal, sekalian mengisi $this->gangguan.
     *
     * @return array<int, array<string, mixed>>
     */
    private function katalog(): array
    {
        $cached = Cache::get('apotek_dpho_katalog');
        if (is_array($cached)) {
            return $cached;
        }

        $hasil = $this->apotek_referensi_dpho();
        $body = $hasil->getData(true);
        $code = $body['metadata']['code'] ?? null;

        if ((string) $code !== '200') {
            $this->gangguan = 'Daftar DPHO belum bisa diakses: ' . ($body['metadata']['message'] ?? 'gangguan BPJS')
                . '. Fitur siap begitu koneksi Apotek Online aktif.';
            return [];
        }

        $list = $body['response']['list'] ?? [];
        if (!is_array($list)) {
            return [];
        }

        // Sukses = cache. Kegagalan TIDAK di-cache, supaya begitu CID aktif LOV
        // langsung pulih tanpa menunggu cache kedaluwarsa.
        Cache::put('apotek_dpho_katalog', $list, now()->addHour());

        return $list;
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }

        $this->gangguan = '';
        $keyword = trim($this->search);
        if (mb_strlen($keyword) < 2) {
            $this->closeAndResetList();
            return;
        }

        $katalog = $this->katalog();
        if ($katalog === []) {
            // $this->gangguan sudah diisi katalog() bila memang gangguan.
            $this->isOpen = $this->gangguan !== '';
            $this->options = [];
            return;
        }

        $keywordUpper = mb_strtoupper($keyword);
        $cocok = [];
        foreach ($katalog as $obat) {
            $kode = (string) ($obat['kodeobat'] ?? '');
            $nama = (string) ($obat['namaobat'] ?? '');
            if (mb_strpos(mb_strtoupper($kode . ' ' . $nama), $keywordUpper) === false) {
                continue;
            }

            // prb/kronis/kemo dikirim BPJS sebagai STRING "True"/"False", bukan boolean.
            $tanda = [];
            foreach (['prb' => 'PRB', 'kronis' => 'Kronis', 'kemo' => 'Kemo'] as $key => $labelTanda) {
                if (strcasecmp((string) ($obat[$key] ?? ''), 'true') === 0) {
                    $tanda[] = $labelTanda;
                }
            }

            $cocok[] = [
                'kode' => $kode,
                'nama' => $nama,
                'label' => $nama ?: '-',
                'hint' => trim("Kode {$kode}" . ($tanda ? '  ·  ' . implode('/', $tanda) : '')
                    . (isset($obat['harga']) ? '  ·  Rp' . number_format((float) $obat['harga'], 0, ',', '.') : '')),
            ];

            if (count($cocok) >= 50) {
                break;
            }
        }

        $this->options = $cocok;
        $this->isOpen = count($cocok) > 0;
        $this->selectedIndex = 0;

        if ($this->isOpen) {
            $this->emitScroll();
        }
    }

    public function choose(int $index): void
    {
        $opt = $this->options[$index] ?? null;
        if (!$opt) {
            return;
        }
        $this->dispatchSelected(['kode' => $opt['kode'], 'nama' => $opt['nama']]);
    }

    public function chooseHighlighted(): void
    {
        if ($this->isOpen) {
            $this->choose($this->selectedIndex);
        }
    }

    public function clearSelected(): void
    {
        if ($this->readonly) {
            return;
        }
        $this->selected = null;
        $this->resetLov();
        // Beri tahu induk pilihan dikosongkan supaya kode_dpho ikut dibersihkan.
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: ['kode' => '', 'nama' => '']);
    }

    protected function dispatchSelected(array $payload): void
    {
        $this->selected = $payload;
        $this->search = '';
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;

        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex', 'gangguan']);
    }

    protected function closeAndResetList(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }

    public function selectNext(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->emitScroll();
    }

    public function selectPrevious(): void
    {
        if (!$this->isOpen || count($this->options) === 0) {
            return;
        }
        $this->selectedIndex = ($this->selectedIndex - 1 + count($this->options)) % count($this->options);
        $this->emitScroll();
    }

    protected function emitScroll(): void
    {
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }
};
?>

<div class="w-full">
    @if ($selected)
        {{-- MODE TERPILIH --}}
        <div
            class="flex items-center justify-between gap-2 px-3 py-2 border rounded-lg bg-surface-soft border-hairline dark:bg-gray-800 dark:border-gray-700">
            <div class="min-w-0">
                <div class="text-sm font-medium truncate text-ink dark:text-gray-100">
                    {{ $selected['nama'] !== '' ? $selected['nama'] : 'Kode DPHO ' . $selected['kode'] }}
                </div>
                <div class="text-xs font-mono text-muted dark:text-gray-400">{{ $selected['kode'] }}</div>
            </div>
            @unless ($readonly)
                <button type="button" wire:click="clearSelected"
                    class="text-xs font-medium underline shrink-0 text-info-deep hover:no-underline dark:text-blue-300">
                    Ubah
                </button>
            @endunless
        </div>
    @else
        {{-- MODE CARI --}}
        <x-lov.dropdown :id="$this->getId()" :is-open="$isOpen" :selected-index="$selectedIndex">
            <div class="relative">
                <x-text-input wire:model.live.debounce.300ms="search" :placeholder="$placeholder" class="w-full"
                    x-on:keydown.arrow-down.prevent="$wire.selectNext()"
                    x-on:keydown.arrow-up.prevent="$wire.selectPrevious()"
                    x-on:keydown.enter.prevent="$wire.chooseHighlighted()" />

                @if ($isOpen)
                    <div
                        class="absolute z-30 w-full mt-1 overflow-y-auto border shadow-lg max-h-64 bg-canvas border-hairline rounded-lg dark:bg-gray-900 dark:border-gray-700">
                        @if ($gangguan !== '')
                            <div class="px-3 py-2 text-xs text-warning-deep dark:text-amber-300">{{ $gangguan }}</div>
                        @else
                            @foreach ($options as $i => $opt)
                                <button type="button" wire:key="dpho-{{ $opt['kode'] }}" wire:click="choose({{ $i }})"
                                    x-ref="lovItem{{ $i }}"
                                    class="block w-full px-3 py-2 text-left transition hover:bg-surface-soft dark:hover:bg-gray-800 {{ $i === $selectedIndex ? 'bg-surface-soft dark:bg-gray-800' : '' }}">
                                    <div class="text-sm text-ink dark:text-gray-100">{{ $opt['label'] }}</div>
                                    <div class="text-xs text-muted dark:text-gray-400">{{ $opt['hint'] }}</div>
                                </button>
                            @endforeach
                        @endif
                    </div>
                @endif
            </div>
        </x-lov.dropdown>
    @endif
</div>
