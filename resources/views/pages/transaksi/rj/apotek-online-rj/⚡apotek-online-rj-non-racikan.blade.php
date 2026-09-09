<?php

use Livewire\Component;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  APOTEK ONLINE RJ — OBAT NON RACIKAN                                     ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * Anak dari ⚡apotek-online-rj-actions, dipisah meniru ⚡eresep-rj-non-racikan:
 * komponen ini BERDIRI SENDIRI — terima :rjNo, baca sendiri, tulis sendiri.
 * Nama properti & method sengaja disamakan dengan e-resep (isFormLocked,
 * findData, syncApotekOnlineJson, save, insertProduct, removeProduct) supaya
 * yang sudah hafal e-resep langsung paham berkas ini.
 *
 * NODE MILIKNYA: `apotekOnline.obat`. Racikan memakai node LAIN
 * (`apotekOnline.obatRacikan`) supaya dua komponen tidak pernah menulis array
 * yang sama — persis alasan e-resep memisah `eresep` dari `eresepRacikan`.
 * Tanpa pemisahan itu, anak yang menyimpan belakangan akan menimpa hasil
 * anak satunya, dan tidak ada pesan apa pun yang menjelaskan.
 *
 * Setiap perubahan langsung tersimpan ke JSON (tidak menunggu tombol), lalu
 * mengabarkan induk lewat `apotek-online-rj.obat-berubah` supaya hitungan
 * "siap kirim" di footer ikut segar.
 */
new class extends Component {
    use EmrRJTrait;

    /** Klaim sudah terkirim → seluruh entri read-only (samakan dgn e-resep). */
    public bool $isFormLocked = false;

    public ?string $rjNo = null;

    /** Baris obat non racikan. Bentuk sama dengan yang dikirim ke BPJS. */
    public array $obatList = [];

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

        $dataDaftarPoliRJ = $this->findDataRJ($rjNo);
        $this->obatList = array_values(array_filter(
            $dataDaftarPoliRJ['apotekOnline']['obat'] ?? [],
            'is_array'
        ));
    }

    /* ═══════════ ENTRI ═══════════ */

    /**
     * Penangkap LOV, dinamai persis seperti target-nya (idiom e-resep).
     * Target sengaja berbeda dari milik racikan: kalau namanya sama, kedua anak
     * menangkap event yang sama dan obat masuk dua kali.
     */
    #[On('lov.selected.apotekOnlineNonRacikan')]
    public function apotekOnlineNonRacikan(string $target, array $payload): void
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

        foreach ($this->obatList as $obat) {
            if (trim((string) ($obat['kodeDpho'] ?? '')) === $kodeDpho) {
                $this->dispatch('toast', type: 'info', message: 'Obat itu sudah ada di daftar non racikan.');
                return;
            }
        }

        $this->obatList[] = [
            'jenis' => 'nonRacikan',
            'noRacikan' => '',
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
        if ($this->isFormLocked || !isset($this->obatList[$indeks])) {
            return;
        }

        unset($this->obatList[$indeks]);
        $this->obatList = array_values($this->obatList);
        $this->save();
    }

    /**
     * Signa/Jml/JHO diikat dengan wire:model.blur — jadi simpan terjadi sekali
     * saat petugas meninggalkan kolom, bukan tiap ketikan.
     */
    public function updated(string $propertyName): void
    {
        if (str_starts_with($propertyName, 'obatList.')) {
            $this->save();
        }
    }

    /* ═══════════ SIMPAN ═══════════ */

    /**
     * Patch HANYA node milik sendiri — key lain (termasuk obatRacikan milik
     * komponen sebelah) tidak tersentuh.
     */
    private function syncApotekOnlineJson(): void
    {
        $dataDaftarPoliRJ = $this->findDataRJ($this->rjNo);

        if (empty($dataDaftarPoliRJ)) {
            throw new \RuntimeException('Data RJ tidak ditemukan, simpan dibatalkan.');
        }

        $dataDaftarPoliRJ['apotekOnline'] ??= [];
        $dataDaftarPoliRJ['apotekOnline']['obat'] = array_values($this->obatList);
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
                $this->syncApotekOnlineJson();
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
    {{-- FORM ENTRI DI ATAS, TABEL DI BAWAH — urutan sama dengan e-resep RJ. --}}
    @unless ($isFormLocked)
        <div class="mb-3">
            <x-input-label value="Tambah obat DPHO (non racikan)" />
            <div class="mt-1 sm:max-w-md">
                <livewire:lov.dpho.lov-dpho target="apotekOnlineNonRacikan"
                    wire:key="lov-apotek-online-non-racikan-{{ $rjNo }}-{{ count($obatList) }}" />
            </div>
        </div>
    @endunless

    <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
        <table class="min-w-full text-sm">
            <thead class="bg-surface-card dark:bg-gray-800">
                <tr class="text-xs font-semibold text-left uppercase text-muted dark:text-gray-300">
                    <th class="px-3 py-2">Obat</th>
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
                @forelse ($obatList as $indeks => $obat)
                    <tr wire:key="apotek-online-non-racikan-{{ $indeks }}"
                        class="border-t border-hairline dark:border-gray-700 {{ ($obat['kodeDpho'] ?? '') === '' ? 'opacity-60' : '' }}">
                        <td class="px-3 py-2">
                            <div class="font-medium text-ink dark:text-gray-100">{{ $obat['nama'] ?? '-' }}</div>
                        </td>
                        <td class="px-3 py-2">
                            @if (filled($obat['kodeDpho'] ?? null))
                                <span class="font-mono text-xs text-success">{{ $obat['kodeDpho'] }}</span>
                            @else
                                <span class="text-xs text-warning-deep dark:text-amber-300">belum dipetakan</span>
                            @endif
                        </td>
                        <td class="px-3 py-2"><x-text-input wire:model.blur="obatList.{{ $indeks }}.signa1" class="w-14 !py-1" :disabled="$isFormLocked" /></td>
                        <td class="px-3 py-2"><x-text-input wire:model.blur="obatList.{{ $indeks }}.signa2" class="w-14 !py-1" :disabled="$isFormLocked" /></td>
                        <td class="px-3 py-2"><x-text-input wire:model.blur="obatList.{{ $indeks }}.jml" class="w-14 !py-1" :disabled="$isFormLocked" /></td>
                        <td class="px-3 py-2"><x-text-input wire:model.blur="obatList.{{ $indeks }}.jho" class="w-14 !py-1" :disabled="$isFormLocked" /></td>
                        @unless ($isFormLocked)
                            <td class="px-3 py-2">
                                <x-hapus-button wire:click="removeProduct({{ $indeks }})" title="Hapus obat" confirm="Hapus obat ini dari klaim?" />
                            </td>
                        @endunless
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-8 text-center text-muted">Belum ada obat non racikan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
