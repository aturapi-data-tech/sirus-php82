<?php
// resources/views/pages/transaksi/ri/emr-ri/automatic-stop-order-ri/rm-automatic-stop-order-ri.blade.php
//
// TAB Automatic Stop Order di EMR RI (di sebelah kanan Observasi) — READ-ONLY.
// Membaca e-resep RI (eresepHdr[] di JSON) + master golongan, lalu menampilkan
// obat aktif yang dipetakan ke golongan beserta batas harinya. Kolom hari berjalan &
// status SENGAJA tidak ditampilkan (keputusan user 2026-09-07): tanpa pembanding
// kapan obat benar-benar dihentikan, angka itu menyesatkan pembaca. Tidak menulis apa pun
// ke JSON; hasil dihitung saat tampil (AutomaticStopOrderHitung). Keputusan kaji
// ulang (LANJUT/STOP) belum ada — tahap berikutnya.

use Livewire\Component;
use Livewire\Attributes\On;
use App\Http\Traits\Txn\Ri\EmrRITrait;
use App\Support\AutomaticStopOrder\AutomaticStopOrderHitung;
use App\Support\AutomaticStopOrder\AutomaticStopOrderMaster;

new class extends Component {
    use EmrRITrait;

    public ?string $riHdrNo = null;
    public bool $masterTersedia = false;
    public array $daftarObat = [];
    public array $obatTidakDipetakan = [];
    public array $ringkasan = [];
    public int $jumlahResepAktif = 0;
    public string $dihitungPada = '';

    #[On('open-rm-automatic-stop-order-ri')]
    public function open(string $riHdrNo): void
    {
        if (empty($riHdrNo)) {
            return;
        }
        $this->riHdrNo = $riHdrNo;
        $this->muat();
    }

    /** Dipanggil juga setelah e-resep tersimpan supaya hitungan ikut terbaru. */
    #[On('refresh-after-ri.saved')]
    public function muatUlang(): void
    {
        if (!empty($this->riHdrNo)) {
            $this->muat();
        }
    }

    private function muat(): void
    {
        $data = $this->findDataRI($this->riHdrNo);
        if (!$data) {
            $this->dispatch('toast', type: 'error', message: 'Data RI tidak ditemukan.');
            return;
        }

        $eresepHdr = (array) ($data['eresepHdr'] ?? []);
        $hasil = AutomaticStopOrderHitung::nilai($eresepHdr, AutomaticStopOrderMaster::muat());

        $this->masterTersedia = $hasil['tersedia'];
        $daftarObat = $hasil['baris'];
        usort($daftarObat, fn(array $a, array $b) => strcmp($a['productName'], $b['productName']));
        $this->daftarObat = $daftarObat;
        $this->obatTidakDipetakan = $hasil['tidakDipetakan'];
        $this->ringkasan = $hasil['ringkasan'];
        $this->jumlahResepAktif = count(array_filter($eresepHdr, fn($hdr) => is_array($hdr) && AutomaticStopOrderHitung::resepAktif($hdr)));
        $this->dihitungPada = now(config('app.timezone'))->format('d/m/Y H:i');
    }

};
?>

<div class="space-y-4">

    {{-- PANDUAN — gaya biru-info standar, default TERTUTUP. --}}
    <div x-data="{ buka: false }"
        class="overflow-hidden border rounded-2xl bg-blue-50 border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
        <button type="button" x-on:click="buka = !buka"
            class="flex items-center justify-between w-full px-4 py-3 text-base font-semibold text-blue-900 transition-colors hover:bg-blue-100 dark:text-blue-200 dark:hover:bg-blue-900/30">
            <span class="flex items-center gap-2">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Tentang Automatic Stop Order
            </span>
            <svg class="w-4 h-4 transition-transform" :class="buka ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="buka" x-collapse class="px-4 pb-4 space-y-2 text-base text-blue-900 dark:text-blue-200">
            <p>
                Order obat golongan tertentu <strong>berhenti otomatis</strong> setelah batas hari kecuali dokter mengkaji
                ulang pasien dan menulis order baru. Tujuannya keamanan pasien dan pengendalian antimikroba.
            </p>
            <ol class="ml-6 space-y-1 list-decimal">
                <li>Yang dihitung hanya resep yang sudah <strong>ditandatangani</strong> dokter atau sudah <strong>terkirim</strong> ke apotek.</li>
                <li><strong>Resep pertama</strong> adalah awal rantai pemberian yang tak terputus; jeda lebih dari {{ AutomaticStopOrderHitung::JEDA_MAKSIMAL_HARI * 24 }} jam antar resep memulai rantai baru.</li>
                <li>Obat yang belum dipetakan ke golongan di <strong>Master Automatic Stop Order</strong> tidak dipantau dan ditampilkan terpisah di bawah.</li>
                <li><strong>Batas Minimal</strong> dan <strong>Batas Stop</strong> adalah waktu hasil hitung: resep pertama ditambah batas hari golongan dikali 24 jam (jam resep ikut dihitung). Bila tanggal batas stop sudah lewat, DPJP mengkaji ulang: lanjutkan dengan order baru atau hentikan.</li>
            </ol>
        </div>
    </div>

    <x-border-form title="Automatic Stop Order" align="start" bgcolor="bg-surface-soft">
        <div class="space-y-4">

            {{-- Ringkasan --}}
            <div class="flex flex-wrap items-center gap-2">
                @if (!$masterTersedia)
                    <x-badge variant="warning">Master Automatic Stop Order belum terpasang / kosong</x-badge>
                @else
                    <x-badge variant="info">{{ count($daftarObat) }} obat terpantau</x-badge>
                @endif
                <span class="text-xs text-muted dark:text-gray-400">{{ $jumlahResepAktif }} resep aktif &middot; dibaca {{ $dihitungPada ?: '-' }}</span>
                <x-secondary-button type="button" wire:click="muatUlang" wire:loading.attr="disabled" class="ml-auto">Muat ulang</x-secondary-button>
            </div>

            {{-- Tabel obat terpantau --}}
            <div class="overflow-x-auto bg-canvas border rounded-2xl border-hairline dark:border-gray-700">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th class="ds-c w-10">No</th>
                            <th>Obat</th>
                            <th>Golongan</th>
                            <th class="ds-c whitespace-nowrap">Resep Pertama</th>
                            <th class="ds-c whitespace-nowrap">Resep Terakhir</th>
                            <th class="ds-c whitespace-nowrap">Batas Minimal</th>
                            <th class="ds-c whitespace-nowrap">Batas Stop</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($daftarObat as $index => $obat)
                            <tr wire:key="automatic-stop-order-ri-{{ $riHdrNo ?? 'new' }}-{{ $obat['productId'] }}">
                                <td class="ds-c ds-td-meta">{{ $index + 1 }}</td>
                                <td>
                                    <div class="ds-td-strong">{{ $obat['productName'] }}</div>
                                    <div class="text-muted dark:text-gray-400">
                                        {{ $obat['signa'] ?: '-' }}
                                        @if ($obat['resepNoList'] !== [])
                                            &middot; Resep #{{ implode(', #', $obat['resepNoList']) }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $obat['golonganNama'] }}</div>
                                </td>
                                <td class="ds-c whitespace-nowrap">{{ $obat['tglMulai'] }}</td>
                                <td class="ds-c whitespace-nowrap">{{ $obat['tglResepTerakhir'] }}</td>
                                <td class="ds-c whitespace-nowrap">
                                    @if ($obat['tglBatasMinimal'] !== null)
                                        <div>{{ $obat['tglBatasMinimal'] }}</div>
                                        <div class="text-xs text-muted dark:text-gray-400">{{ $obat['batasMinimalHari'] }} x 24 jam</div>
                                    @else
                                        <span class="text-muted-soft">-</span>
                                    @endif
                                </td>
                                <td class="ds-c whitespace-nowrap">
                                    <div class="font-semibold">{{ $obat['tglBatasStop'] }}</div>
                                    <div class="text-xs text-muted dark:text-gray-400">{{ $obat['batasHari'] }} x 24 jam</div>
                                </td>
                                <td class="max-w-md">
                                    @if ($obat['catatanProduk'] !== '')
                                        <div class="font-medium">{{ $obat['catatanProduk'] }}</div>
                                    @endif
                                    <div class="text-sm text-muted dark:text-gray-400">{{ $obat['keterangan'] ?: '-' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="ds-c italic text-muted-soft">
                                    @if (!$masterTersedia)
                                        Master belum tersedia &mdash; jalankan DDL dan <code>php artisan automatic-stop-order:seed</code>.
                                    @elseif ($jumlahResepAktif === 0)
                                        Belum ada resep aktif (ditandatangani / terkirim) pada kunjungan ini.
                                    @else
                                        Tidak ada obat aktif yang termasuk golongan Automatic Stop Order.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Obat aktif yang belum dipetakan — supaya apoteker tahu apa yang luput dari pantauan --}}
            @if ($obatTidakDipetakan !== [])
                <div class="text-sm text-muted dark:text-gray-400">
                    <span class="font-semibold">Tidak dipantau</span> (belum dipetakan ke golongan, atau golongannya nonaktif):
                    {{ implode(', ', array_column($obatTidakDipetakan, 'productName')) }}
                </div>
            @endif

        </div>
    </x-border-form>
</div>
