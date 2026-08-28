<?php

use Livewire\Component;
use Livewire\Attributes\Session;
use App\Http\Traits\BPJS\ApotekTrait;

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFERENSI APOTEK ONLINE BPJS                                            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Layar penelusuran untuk endpoint Apotek Online yang sifatnya MEMBACA saja.
 * Satu halaman bertab, bukan enam halaman terpisah: keenamnya sama-sama
 * "ketik kata kunci, lihat balasan BPJS", dan memisahnya cuma menambah menu.
 *
 * Tiap tab memenuhi satu modul Checklist Pengujian Integrasi Sistem BPJS
 * (Web Service Apotek 2.0) yang menuntut tangkapan layar per butir:
 *
 *   Tab Setting Apotek   → modul 1  GET REFERENSI APOTEK
 *   Tab Faskes           → modul 2  GET REFERENSI PPK
 *   Tab Poli             → modul 4  GET REFERENSI POLI
 *   Tab Spesialis        → modul 6  GET REFERENSI SPESIALIS
 *   Tab Obat DPHO        → modul 3  GET REFERENSI OBAT (sisi referensi/obat)
 *   Tab Riwayat Peserta  → modul 15 DAFTAR PELAYANAN OBAT
 *
 * TAB DIKENDALIKAN DARI SERVER, bukan Alpine x-show. Isi tiap tab datang dari
 * panggilan HTTP dan berubah-ubah; island Alpine yang di-morph berulang sudah
 * pernah membuat layar lain hang di repo ini.
 *
 * TIDAK ADA PEMANGGILAN OTOMATIS SAAT HALAMAN DIBUKA. Setiap tarikan menembak
 * BPJS sungguhan dan terhitung kuota, jadi selalu menunggu petugas menekan
 * tombolnya sendiri.
 */
new class extends Component {
    use ApotekTrait;

    #[Session(key: 'apotek-online-referensi-tab')]
    public string $tab = 'setting';

    /** Kata kunci per tab — dipisah supaya berpindah tab tak menghapus isian sebelumnya. */
    public string $cariFaskes = '';
    public string $jenisFaskes = '2';
    public string $cariPoli = '';
    public string $kodeApotek = '';

    public string $jenisObat = '2';
    public string $tglResepObat = '';
    public string $filterObat = '';

    public string $noKartu = '';
    public string $riwayatAwal = '';
    public string $riwayatAkhir = '';

    /** Hasil tarikan terakhir per tab: ['ok' => bool, 'pesan' => string, 'baris' => array]. */
    public array $hasil = [];

    public bool $sedangTarik = false;

    public function mount(): void
    {
        $this->kodeApotek = (string) env('APOTEK_KDPPK');
        $this->tglResepObat = now()->format('Y-m-d');
        $this->riwayatAwal = now()->startOfMonth()->format('Y-m-d');
        $this->riwayatAkhir = now()->format('Y-m-d');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['setting', 'faskes', 'poli', 'spesialis', 'obat', 'riwayat'], true)) {
            $this->tab = $tab;
            $this->hasil = [];
        }
    }

    /* ═══════════ PENARIKAN ═══════════ */

    /**
     * Bungkus seragam untuk keenam tarikan: jalankan, terjemahkan balasan BPJS
     * jadi bentuk yang sama, dan pastikan kegagalan tetap terbaca petugas.
     *
     * Balasan Apotek Online tidak seragam bentuknya — ada yang mengembalikan
     * daftar langsung, ada yang membungkusnya dalam satu key. Perataannya
     * dikerjakan di satu tempat ini supaya tabel penampilnya cukup satu.
     */
    private function tarik(string $judul, callable $panggil): void
    {
        $this->sedangTarik = true;
        $this->hasil = [];

        try {
            $balasan = $panggil()->getData(true);
        } catch (\Throwable $e) {
            $this->hasil = ['ok' => false, 'pesan' => 'Gagal memanggil BPJS: ' . $e->getMessage(), 'baris' => []];
            $this->sedangTarik = false;
            return;
        }

        $kode = (string) ($balasan['metadata']['code'] ?? '');
        $pesan = (string) ($balasan['metadata']['message'] ?? 'tanpa keterangan');

        if ($kode !== '200') {
            $this->hasil = ['ok' => false, 'pesan' => $judul . ' ditolak — ' . $pesan, 'baris' => []];
            $this->sedangTarik = false;
            return;
        }

        $this->hasil = [
            'ok' => true,
            'pesan' => $judul . ' berhasil — ' . $pesan,
            'baris' => self::ratakan($balasan['response'] ?? []),
        ];
        $this->sedangTarik = false;
    }

    /**
     * Ratakan respons jadi daftar baris asosiatif.
     *
     * Tiga bentuk yang mungkin datang:
     *   [ {..}, {..} ]              daftar langsung
     *   { "list": [ {..}, {..} ] }  daftar terbungkus satu key
     *   { "a": 1, "b": 2 }          satu objek tunggal (mis. Setting Apotek)
     */
    private static function ratakan(mixed $response): array
    {
        if (!is_array($response) || $response === []) {
            return [];
        }

        if (array_is_list($response)) {
            return array_values(array_filter($response, 'is_array'));
        }

        foreach ($response as $isi) {
            if (is_array($isi) && array_is_list($isi) && $isi !== [] && is_array($isi[0])) {
                return $isi;
            }
        }

        return [$response];
    }

    /* ── modul 1 ── */
    public function tarikSetting(): void
    {
        $this->tarik('Setting Apotek', fn() => $this->apotek_referensi_setting($this->kodeApotek ?: null));
    }

    /* ── modul 2 ── */
    public function tarikFaskes(): void
    {
        if (trim($this->cariFaskes) === '') {
            $this->dispatch('toast', type: 'error', message: 'Isi nama faskes lebih dulu.');
            return;
        }
        $this->tarik('Referensi Faskes', fn() => $this->apotek_referensi_faskes($this->jenisFaskes, trim($this->cariFaskes)));
    }

    /* ── modul 4 ── */
    public function tarikPoli(): void
    {
        if (trim($this->cariPoli) === '') {
            $this->dispatch('toast', type: 'error', message: 'Isi nama poli lebih dulu.');
            return;
        }
        $this->tarik('Referensi Poli', fn() => $this->apotek_referensi_poli(trim($this->cariPoli)));
    }

    /* ── modul 6 ── */
    public function tarikSpesialis(): void
    {
        $this->tarik('Referensi Spesialis', fn() => $this->apotek_referensi_spesialistik());
    }

    /* ── modul 3 (sisi referensi/obat) ── */
    public function tarikObat(): void
    {
        $this->tarik('Referensi Obat', fn() => $this->apotek_referensi_obat(
            $this->jenisObat,
            $this->tglResepObat,
            trim($this->filterObat)
        ));
    }

    /* ── modul 15 ── */
    public function tarikRiwayat(): void
    {
        if (trim($this->noKartu) === '') {
            $this->dispatch('toast', type: 'error', message: 'Isi nomor kartu peserta lebih dulu.');
            return;
        }
        $this->tarik('Riwayat Pelayanan Obat', fn() => $this->apotek_pelayanan_riwayat(
            $this->riwayatAwal,
            $this->riwayatAkhir,
            trim($this->noKartu)
        ));
    }

    /** Nama kolom tabel hasil, diambil dari baris pertama. */
    public function kolomHasil(): array
    {
        $baris = $this->hasil['baris'] ?? [];

        return $baris === [] ? [] : array_keys($baris[0]);
    }
};
?>

<div>
    <x-page-title title="Referensi Apotek Online"
        subtitle="Penelusuran data acuan BPJS — setting apotek, faskes, poli, spesialis, obat DPHO & riwayat obat peserta" />

    <div class="px-4">

        {{-- TAB: dikendalikan server. Tiap tab = satu modul checklist BPJS. --}}
        <x-tabs variant="underline">
            <x-tab :active="$tab === 'setting'" wire:click="setTab('setting')">Setting Apotek</x-tab>
            <x-tab :active="$tab === 'faskes'" wire:click="setTab('faskes')">Faskes</x-tab>
            <x-tab :active="$tab === 'poli'" wire:click="setTab('poli')">Poli</x-tab>
            <x-tab :active="$tab === 'spesialis'" wire:click="setTab('spesialis')">Spesialis</x-tab>
            <x-tab :active="$tab === 'obat'" wire:click="setTab('obat')">Obat DPHO</x-tab>
            <x-tab :active="$tab === 'riwayat'" wire:click="setTab('riwayat')">Riwayat Peserta</x-tab>
        </x-tabs>

        {{-- FORM PENCARIAN — isinya ikut tab, tombol tarik selalu di ujung kanan --}}
        <div class="p-4 mt-3 border rounded-2xl bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
            <div class="flex flex-wrap items-end gap-3">

                @if ($tab === 'setting')
                    <div class="w-full sm:w-64">
                        <x-input-label value="Kode Apotek (KDPPK)" />
                        <x-text-input wire:model="kodeApotek" class="w-full mt-1" placeholder="mis. 0184A012" />
                    </div>
                    <p class="flex-1 min-w-[16rem] text-xs text-muted dark:text-gray-400">
                        Balasannya memuat identitas apoteker &amp; verifikator, dan penanda
                        <strong>checkstock</strong> — bila bernilai aktif, BPJS ikut memvalidasi stok saat obat disimpan.
                    </p>
                    <x-primary-button type="button" class="shrink-0" wire:click="tarikSetting"
                        wire:loading.attr="disabled" wire:target="tarikSetting">
                        <span wire:loading.remove wire:target="tarikSetting">Tarik Setting</span>
                        <span wire:loading wire:target="tarikSetting"><x-loading /> Menarik...</span>
                    </x-primary-button>
                @endif

                @if ($tab === 'faskes')
                    <div class="w-full sm:w-40">
                        <x-input-label value="Jenis Faskes" />
                        <x-select-input wire:model="jenisFaskes" class="w-full mt-1">
                            <option value="1">1 — FKTP</option>
                            <option value="2">2 — FKRTL</option>
                        </x-select-input>
                    </div>
                    <div class="w-full sm:w-72">
                        <x-input-label value="Nama Faskes" />
                        <x-text-input wire:model="cariFaskes" class="w-full mt-1" placeholder="mis. MADINAH"
                            wire:keydown.enter="tarikFaskes" />
                    </div>
                    <x-primary-button type="button" class="ml-auto shrink-0" wire:click="tarikFaskes"
                        wire:loading.attr="disabled" wire:target="tarikFaskes">
                        <span wire:loading.remove wire:target="tarikFaskes">Cari Faskes</span>
                        <span wire:loading wire:target="tarikFaskes"><x-loading /> Menarik...</span>
                    </x-primary-button>
                @endif

                @if ($tab === 'poli')
                    <div class="w-full sm:w-72">
                        <x-input-label value="Nama Poli" />
                        <x-text-input wire:model="cariPoli" class="w-full mt-1" placeholder="mis. JANTUNG"
                            wire:keydown.enter="tarikPoli" />
                    </div>
                    <x-primary-button type="button" class="ml-auto shrink-0" wire:click="tarikPoli"
                        wire:loading.attr="disabled" wire:target="tarikPoli">
                        <span wire:loading.remove wire:target="tarikPoli">Cari Poli</span>
                        <span wire:loading wire:target="tarikPoli"><x-loading /> Menarik...</span>
                    </x-primary-button>
                @endif

                @if ($tab === 'spesialis')
                    <p class="flex-1 min-w-[16rem] text-xs text-muted dark:text-gray-400">
                        Daftar spesialistik tidak memerlukan kata kunci — BPJS mengembalikan seluruhnya sekaligus.
                    </p>
                    <x-primary-button type="button" class="shrink-0" wire:click="tarikSpesialis"
                        wire:loading.attr="disabled" wire:target="tarikSpesialis">
                        <span wire:loading.remove wire:target="tarikSpesialis">Tarik Daftar Spesialis</span>
                        <span wire:loading wire:target="tarikSpesialis"><x-loading /> Menarik...</span>
                    </x-primary-button>
                @endif

                @if ($tab === 'obat')
                    <div class="w-full sm:w-52">
                        <x-input-label value="Jenis Obat" />
                        <x-select-input wire:model="jenisObat" class="w-full mt-1">
                            <option value="1">1 — Obat PRB</option>
                            <option value="2">2 — Obat Kronis Belum Stabil</option>
                            <option value="3">3 — Obat Kemoterapi</option>
                        </x-select-input>
                    </div>
                    <div class="w-full sm:w-44">
                        <x-input-label value="Tanggal Resep" />
                        <x-text-input type="date" wire:model="tglResepObat" class="w-full mt-1" />
                    </div>
                    <div class="w-full sm:w-56">
                        <x-input-label value="Filter Nama Obat" />
                        <x-text-input wire:model="filterObat" class="w-full mt-1" placeholder="opsional"
                            wire:keydown.enter="tarikObat" />
                    </div>
                    <x-primary-button type="button" class="ml-auto shrink-0" wire:click="tarikObat"
                        wire:loading.attr="disabled" wire:target="tarikObat">
                        <span wire:loading.remove wire:target="tarikObat">Tarik Obat</span>
                        <span wire:loading wire:target="tarikObat"><x-loading /> Menarik...</span>
                    </x-primary-button>
                @endif

                @if ($tab === 'riwayat')
                    <div class="w-full sm:w-60">
                        <x-input-label value="No. Kartu Peserta" />
                        <x-text-input wire:model="noKartu" class="w-full mt-1" placeholder="13 digit"
                            wire:keydown.enter="tarikRiwayat" />
                    </div>
                    <div class="w-full sm:w-44">
                        <x-input-label value="Tanggal Awal" />
                        <x-text-input type="date" wire:model="riwayatAwal" class="w-full mt-1" />
                    </div>
                    <div class="w-full sm:w-44">
                        <x-input-label value="Tanggal Akhir" />
                        <x-text-input type="date" wire:model="riwayatAkhir" class="w-full mt-1" />
                    </div>
                    <x-primary-button type="button" class="ml-auto shrink-0" wire:click="tarikRiwayat"
                        wire:loading.attr="disabled" wire:target="tarikRiwayat">
                        <span wire:loading.remove wire:target="tarikRiwayat">Tarik Riwayat</span>
                        <span wire:loading wire:target="tarikRiwayat"><x-loading /> Menarik...</span>
                    </x-primary-button>
                @endif

            </div>
        </div>

        {{-- KETERANGAN HASIL — sukses maupun ditolak sama-sama ditampilkan apa adanya,
           | karena pesan penolakan BPJS itulah yang perlu ikut ter-tangkapan-layar. --}}
        @if ($hasil)
            <div class="px-4 py-3 mt-3 text-sm border rounded-xl
                {{ $hasil['ok']
                    ? 'bg-success/10 border-success/30 text-success'
                    : 'bg-error/10 border-error/30 text-error-deep dark:text-red-300' }}">
                {{ $hasil['pesan'] }}
            </div>
        @endif

        {{-- TABEL HASIL — kolomnya mengikuti apa yang BPJS kirim, tidak dipetakan ulang.
           | Nama field aslinya justru yang dicocokkan penguji dengan katalog mereka. --}}
        <div class="mt-3 overflow-x-auto border shadow-sm rounded-2xl bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-surface-card dark:bg-gray-800">
                    <tr class="text-xs font-semibold tracking-wide text-left uppercase text-muted dark:text-gray-300">
                        @forelse ($this->kolomHasil() as $kolom)
                            <th class="px-4 py-3 border-b whitespace-nowrap border-hairline dark:border-gray-700">{{ $kolom }}</th>
                        @empty
                            <th class="px-4 py-3 border-b border-hairline dark:border-gray-700">Hasil</th>
                        @endforelse
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hasil['baris'] ?? [] as $indeks => $baris)
                        <tr wire:key="hasil-{{ $tab }}-{{ $indeks }}" class="border-t border-hairline dark:border-gray-700">
                            @foreach ($this->kolomHasil() as $kolom)
                                <td class="px-4 py-2.5 align-top text-body dark:text-gray-300">
                                    {{ is_scalar($baris[$kolom] ?? null) ? $baris[$kolom] : json_encode($baris[$kolom] ?? null, JSON_UNESCAPED_UNICODE) }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(1, count($this->kolomHasil())) }}" class="px-4 py-14 text-center">
                                <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada hasil</p>
                                <p class="mt-1 text-xs text-muted-soft">
                                    Tekan tombol tarik di atas. Tiap penarikan menembak BPJS sungguhan, jadi tidak dijalankan otomatis.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (($hasil['baris'] ?? []) !== [])
            <p class="mt-2 text-xs text-muted-soft">{{ count($hasil['baris']) }} baris diterima dari BPJS.</p>
        @endif

    </div>
</div>
