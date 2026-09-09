<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  APOTEK ONLINE RJ — OBAT RACIKAN                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Kembaran ⚡apotek-online-rj-non-racikan, dipisah meniru ⚡eresep-rj-racikan.
 * Nama properti & method mengikuti e-resep (isFormLocked, noRacikan, findData,
 * syncApotekOnlineRacikanJson, save, insertProduct, removeProduct).
 *
 * NODE MILIKNYA: `apotekOnline.obatRacikan` + `apotekOnline.permintaanRacikan`.
 * Sengaja BUKAN node yang sama dengan non-racikan — lihat catatan di komponen
 * sebelah soal dua anak yang saling menimpa.
 *
 * BENTUK DATA. Tiap baris adalah satu BAHAN, ditandai `noRacikan` (R1, R2, ...),
 * sama seperti e-resep racikan. BPJS menerimanya per bahan lewat
 * obatracikan/v3/insert dengan JNSROBT = kode racikan, jadi racikan tidak perlu
 * disimpan sebagai objek tersendiri — cukup penanda di baris, ditambah PERMINTAAN
 * yang disimpan terpisah per racikan.
 *
 * PERMINTAAN milik RACIKAN (berapa bungkus diminta), bukan milik tiap bahan.
 * Kalau ditaruh di baris, mengubah satu bahan bisa membuat racikan jadi tak
 * konsisten dan yang terkirim ke BPJS tergantung bahan mana yang kebetulan
 * dibaca duluan.
 */
new class extends Component {
    use EmrRJTrait;

    /** Klaim sudah terkirim → seluruh entri read-only (samakan dgn e-resep). */
    public bool $isFormLocked = false;

    public ?string $rjNo = null;

    /** Baris bahan racikan; tiap baris membawa noRacikan-nya sendiri. */
    public array $bahanList = [];

    /** ['R1' => 10, 'R2' => 30] — jumlah permintaan per racikan. */
    public array $permintaanRacikan = [];

    /** Racikan yang sedang diisi; bahan dari LOV masuk ke sini (idiom e-resep). */
    public string $noRacikan = '';

    public function mount(): void
    {
        $this->findData($this->rjNo);
    }

    /* ═══════════ MUAT ═══════════ */

    #[On('apotek-online-rj.muat-ulang')]
    public function refreshData(): void
    {
        $this->findData($this->rjNo);
    }

    protected function findData($rjNo): void
    {
        if (blank($rjNo)) {
            return;
        }

        $apotekOnline = $this->findDataRJ($rjNo)['apotekOnline'] ?? [];

        $this->bahanList = array_values(array_filter($apotekOnline['obatRacikan'] ?? [], 'is_array'));
        $this->permintaanRacikan = array_filter(
            (array) ($apotekOnline['permintaanRacikan'] ?? []),
            fn($jumlah) => is_numeric($jumlah)
        );

        // Racikan aktif yang sudah tidak ada (mis. baru dihapus) jangan dipertahankan.
        $daftarNoRacikan = array_keys($this->kelompokkanRacikan());
        if ($this->noRacikan === '' || !in_array($this->noRacikan, $daftarNoRacikan, true)) {
            $this->noRacikan = (string) ($daftarNoRacikan[0] ?? '');
        }
    }

    /* ═══════════ RACIKAN ═══════════ */

    /**
     * Bahan dikelompokkan per noRacikan, INDEKS ASLI dibawa serta supaya
     * removeProduct()/wire:model tetap menunjuk baris yang benar setelah
     * dikelompokkan. Namanya menggemakan EresepJson::kelompokkanRacikan().
     *
     * @return array<string, array<int, array{indeks:int, bahan:array}>>
     */
    public function kelompokkanRacikan(): array
    {
        $kelompok = [];
        foreach ($this->bahanList as $indeks => $bahan) {
            $kelompok[(string) ($bahan['noRacikan'] ?? '-')][] = ['indeks' => $indeks, 'bahan' => $bahan];
        }
        ksort($kelompok);

        return $kelompok;
    }

    /** Nomor racikan berikutnya: R1, R2, ... (lompati yang sudah terpakai). */
    public function tambahRacikan(): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $terpakai = array_keys($this->kelompokkanRacikan());
        $nomor = 1;
        while (in_array('R' . $nomor, $terpakai, true)) {
            $nomor++;
        }

        $this->noRacikan = 'R' . $nomor;
        $this->permintaanRacikan[$this->noRacikan] = 1;
        $this->save();
    }

    public function pilihRacikan(string $noRacikan): void
    {
        $this->noRacikan = $noRacikan;
    }

    /** Hapus satu racikan beserta SELURUH bahannya. */
    public function hapusRacikan(string $noRacikan): void
    {
        if ($this->isFormLocked) {
            return;
        }

        $this->bahanList = array_values(array_filter(
            $this->bahanList,
            fn($bahan) => (string) ($bahan['noRacikan'] ?? '') !== $noRacikan
        ));
        unset($this->permintaanRacikan[$noRacikan]);

        if ($this->noRacikan === $noRacikan) {
            $this->noRacikan = (string) (array_key_first($this->kelompokkanRacikan()) ?? '');
        }

        $this->save();
    }

    /* ═══════════ ENTRI BAHAN ═══════════ */

    /** Penangkap LOV — lihat catatan target di komponen non racikan. */
    #[On('lov.selected.apotekOnlineRacikan')]
    public function apotekOnlineRacikan(string $target, array $payload): void
    {
        $this->insertProduct(
            trim((string) ($payload['kode'] ?? '')),
            (string) ($payload['nama'] ?? '')
        );
    }

    public function insertProduct(string $kodeDpho, string $productName): void
    {
        if ($this->isFormLocked || $kodeDpho === '') {
            return;
        }

        // Belum ada racikan sama sekali: buat R1 supaya petugas tidak tersangkut.
        if ($this->noRacikan === '') {
            $this->tambahRacikan();
        }

        // Dobel dicegah HANYA di dalam racikan yang sama. Bahan yang sama sah
        // dipakai di dua racikan berbeda — itu justru lazim.
        foreach ($this->kelompokkanRacikan()[$this->noRacikan] ?? [] as $baris) {
            if (trim((string) ($baris['bahan']['kodeDpho'] ?? '')) === $kodeDpho) {
                $this->dispatch('toast', type: 'info', message: 'Bahan itu sudah ada di racikan ' . $this->noRacikan . '.');
                return;
            }
        }

        $this->bahanList[] = [
            'jenis' => 'racikan',
            'noRacikan' => $this->noRacikan,
            'productId' => '',
            'nama' => $productName !== '' ? $productName : $kodeDpho,
            'kodeDpho' => $kodeDpho,
            'signa1' => '1',
            'signa2' => '1',
            'jml' => '',
            'jho' => '',
            'catatan' => '',
        ];

        $this->save();
    }

    public function removeProduct(int $indeks): void
    {
        if ($this->isFormLocked || !isset($this->bahanList[$indeks])) {
            return;
        }

        unset($this->bahanList[$indeks]);
        $this->bahanList = array_values($this->bahanList);
        $this->save();
    }

    public function updated(string $propertyName): void
    {
        if (str_starts_with($propertyName, 'bahanList.') || str_starts_with($propertyName, 'permintaanRacikan.')) {
            $this->save();
        }
    }

    /* ═══════════ SIMPAN ═══════════ */

    /** Patch HANYA node milik sendiri — `obat` (non racikan) tak tersentuh. */
    private function syncApotekOnlineRacikanJson(): void
    {
        $dataDaftarPoliRJ = $this->findDataRJ($this->rjNo);

        if (empty($dataDaftarPoliRJ)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        $dataDaftarPoliRJ['apotekOnline'] ??= [];
        $dataDaftarPoliRJ['apotekOnline']['obatRacikan'] = array_values($this->bahanList);
        $dataDaftarPoliRJ['apotekOnline']['permintaanRacikan'] = $this->permintaanRacikan;
        $dataDaftarPoliRJ['apotekOnline']['ubahAt'] = now(env('APP_TIMEZONE'))->format('Y-m-d H:i:s');
        $dataDaftarPoliRJ['apotekOnline']['ubahOleh'] = (string) (auth()->user()->myuser_name ?? auth()->user()->name ?? '-');

        $this->updateJsonRJ($this->rjNo, $dataDaftarPoliRJ);
    }

    public function save(): void
    {
        if ($this->isFormLocked || blank($this->rjNo)) {
            return;
        }

        try {
            DB::transaction(function () {
                $this->lockRJRow($this->rjNo);
                $this->syncApotekOnlineRacikanJson();
            });
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal menyimpan: ' . $e->getMessage());
            return;
        }

        $this->dispatch('apotek-online-rj.obat-berubah');
    }
};
?>

<div>

    {{-- FORM ENTRI DI ATAS, DAFTAR RACIKAN DI BAWAH — urutan sama dengan e-resep RJ.
       | Keterangan, LOV bahan, dan tombol racikan baru dijadikan SATU baris:
       | keterangan mengisi sisa ruang (flex-1), LOV dipatok 18rem karena isinya
       | nama obat yang panjang, tombol selebar isinya sendiri. items-end supaya
       | dasar tombol sejajar dengan dasar kotak LOV, bukan dengan labelnya. --}}
    @unless ($isFormLocked)
        <div class="flex flex-wrap items-end gap-3 mb-3">
            <div class="w-full sm:w-72 shrink-0">
                <x-input-label value="Tambah bahan ke racikan {{ $noRacikan ?: '(baru)' }}" />
                <div class="mt-1">
                    <livewire:lov.dpho.lov-dpho target="apotekOnlineRacikan"
                        wire:key="lov-apotek-online-racikan-{{ $rjNo }}-{{ $noRacikan }}-{{ count($bahanList) }}" />
                </div>
            </div>

            <p class="flex-1 min-w-[14rem] text-xs text-muted dark:text-gray-400">
                Bahan yang dipilih masuk ke racikan yang sedang aktif. Tiap racikan dikirim
                sebagai satu JNSROBT dengan jumlah permintaannya sendiri.
            </p>

            <x-outline-button type="button" class="shrink-0" wire:click="tambahRacikan">Racikan baru</x-outline-button>
        </div>
    @endunless

    <div class="space-y-3">
        @forelse ($this->kelompokkanRacikan() as $noRacikanBaris => $daftarBahan)
            <div wire:key="apotek-online-racikan-{{ $noRacikanBaris }}"
                class="border rounded-lg {{ $noRacikan === $noRacikanBaris ? 'border-brand dark:border-brand-lime' : 'border-hairline dark:border-gray-700' }}">

                <div class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-hairline dark:border-gray-700 bg-surface-card dark:bg-gray-800">
                    <span class="text-sm font-semibold text-ink dark:text-gray-100">Racikan {{ $noRacikanBaris }}</span>

                    @if ($noRacikan === $noRacikanBaris)
                        <x-badge variant="success">sedang diisi</x-badge>
                    @elseif (!$isFormLocked)
                        <x-outline-button type="button" wire:click="pilihRacikan('{{ $noRacikanBaris }}')">Isi racikan ini</x-outline-button>
                    @endif

                    <div class="flex items-center gap-2 ml-auto">
                        <x-input-label value="Permintaan" class="!mb-0 text-xs" />
                        <x-text-input wire:model.blur="permintaanRacikan.{{ $noRacikanBaris }}" class="w-20 !py-1" :disabled="$isFormLocked" />

                        @unless ($isFormLocked)
                            <x-outline-button type="button"
                                wire:click="hapusRacikan('{{ $noRacikanBaris }}')"
                                wire:confirm="Hapus racikan {{ $noRacikanBaris }} beserta seluruh bahannya?"
                                wire:loading.attr="disabled"
                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300
                                       dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30
                                       dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1"
                                title="Hapus racikan">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </x-outline-button>
                        @endunless
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-card dark:bg-gray-800">
                            <tr class="text-xs font-semibold text-left uppercase text-muted dark:text-gray-300">
                                <th class="px-3 py-2">Bahan</th>
                                <th class="px-3 py-2">Kode DPHO</th>
                                <th class="w-16 px-3 py-2">Signa1</th>
                                <th class="w-16 px-3 py-2">Signa2</th>
                                <th class="w-16 px-3 py-2">Jml</th>
                                <th class="w-16 px-3 py-2">JHO</th>
                                @unless ($isFormLocked)
                                    <th class="w-10 px-3 py-2"></th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftarBahan as $baris)
                                @php $indeks = $baris['indeks']; $bahan = $baris['bahan']; @endphp
                                <tr wire:key="apotek-online-bahan-{{ $indeks }}"
                                    class="border-t border-hairline dark:border-gray-700 {{ ($bahan['kodeDpho'] ?? '') === '' ? 'opacity-60' : '' }}">
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-ink dark:text-gray-100">{{ $bahan['nama'] ?? '-' }}</div>
                                    </td>
                                    <td class="px-3 py-2">
                                        @if (filled($bahan['kodeDpho'] ?? null))
                                            <span class="font-mono text-xs text-success">{{ $bahan['kodeDpho'] }}</span>
                                        @else
                                            <span class="text-xs text-warning-deep dark:text-amber-300">belum dipetakan</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2"><x-text-input wire:model.blur="bahanList.{{ $indeks }}.signa1" class="w-14 !py-1" :disabled="$isFormLocked" /></td>
                                    <td class="px-3 py-2"><x-text-input wire:model.blur="bahanList.{{ $indeks }}.signa2" class="w-14 !py-1" :disabled="$isFormLocked" /></td>
                                    <td class="px-3 py-2"><x-text-input wire:model.blur="bahanList.{{ $indeks }}.jml" class="w-14 !py-1" :disabled="$isFormLocked" /></td>
                                    <td class="px-3 py-2"><x-text-input wire:model.blur="bahanList.{{ $indeks }}.jho" class="w-14 !py-1" :disabled="$isFormLocked" /></td>
                                    @unless ($isFormLocked)
                                        <td class="px-3 py-2">
                                            <x-hapus-button wire:click="removeProduct({{ $indeks }})" title="Hapus obat" confirm="Hapus obat ini dari klaim?" />
                                        </td>
                                    @endunless
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            {{-- Kosong pun tabel tetap tampil — susunan kolomnya langsung terlihat, dan
               | seragam dengan tabel non racikan serta aturan baku modul dokumen. --}}
            <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface-card dark:bg-gray-800">
                        <tr class="text-xs font-semibold text-left uppercase text-muted dark:text-gray-300">
                            <th class="px-3 py-2">Bahan</th>
                            <th class="px-3 py-2">Kode DPHO</th>
                            <th class="w-16 px-3 py-2">Signa1</th>
                            <th class="w-16 px-3 py-2">Signa2</th>
                            <th class="w-16 px-3 py-2">Jml</th>
                            <th class="w-16 px-3 py-2">JHO</th>
                            @unless ($isFormLocked)
                                <th class="w-10 px-3 py-2"></th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="{{ $isFormLocked ? 6 : 7 }}" class="px-3 py-8 text-center text-muted">
                                Belum ada racikan. Tekan <strong>Racikan baru</strong> untuk memulai.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endforelse
    </div>

</div>
