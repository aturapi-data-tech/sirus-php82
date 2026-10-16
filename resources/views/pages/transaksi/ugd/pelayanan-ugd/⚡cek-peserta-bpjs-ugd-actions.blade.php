<?php
// resources/views/pages/transaksi/ugd/pelayanan-ugd/cek-peserta-bpjs-ugd-actions.blade.php
//
// Sibling component pelayanan-ugd untuk cek status kepesertaan BPJS via
// VClaim — terima input No Kartu (13 digit) atau NIK (16 digit), tampilkan
// kartu detail peserta (nama, NIK, no kartu, tgl lahir, status AKTIF/NON,
// jenis peserta, hak kelas, faskes, masa berlaku TMT-TAT).
//
// Dipakai petugas UGD untuk verifikasi keaktifan kartu peserta saat
// pelayanan. Dipanggil dari tombol "Cek BPJS" di topbar
// ⚡pelayanan-ugd.blade.php yang dispatch event 'cek-peserta-bpjs.open'.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    use VclaimTrait;

    public string $noKartuBPJS = '';
    public array $pesertaBPJS = [];

    #[On('cek-peserta-bpjs.open')]
    public function open(): void
    {
        $this->reset(['noKartuBPJS', 'pesertaBPJS']);
        $this->dispatch('open-modal', name: 'cek-peserta-bpjs-ugd');
    }

    public function cekPeserta(): void
    {
        $keyword = trim($this->noKartuBPJS);

        if ($keyword === '') {
            $this->dispatch('toast', type: 'warning', message: 'Nomor Kartu BPJS / NIK wajib diisi.');
            return;
        }

        if (!ctype_digit($keyword)) {
            $this->dispatch('toast', type: 'error', message: 'Input harus berupa angka.');
            return;
        }

        $tanggal = Carbon::now()->format('Y-m-d');
        $length = strlen($keyword);

        try {
            if ($length === 13) {
                $response = $this->peserta_nomorkartu($keyword, $tanggal);
            } elseif ($length === 16) {
                $response = $this->peserta_nik($keyword, $tanggal);
            } else {
                $this->dispatch('toast', type: 'error', message: 'Nomor BPJS harus 13 digit atau NIK 16 digit.');
                return;
            }

            $content = $response->getOriginalContent();
            $code = (int) ($content['metadata']['code'] ?? 500);

            if ($code === 200) {
                $this->pesertaBPJS = $content['response']['peserta'] ?? [];
                $this->dispatch('toast', type: 'success', message: $content['metadata']['message'] ?? 'Data peserta ditemukan.');
            } else {
                $this->pesertaBPJS = [];
                $this->dispatch('toast', type: 'error', message: $content['metadata']['message'] ?? 'Data peserta tidak ditemukan.');
            }
        } catch (\Throwable $e) {
            $this->pesertaBPJS = [];
            $this->dispatch('toast', type: 'error', message: 'Gagal menghubungi VClaim: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->dispatch('close-modal', name: 'cek-peserta-bpjs-ugd');
        $this->reset(['noKartuBPJS', 'pesertaBPJS']);
    }
}; ?>

<x-modal name="cek-peserta-bpjs-ugd" size="2xl" focusable padding="p-0">

    {{-- ═══════════ HEADER ═══════════ --}}
    <x-modul-dokumen.header judul="Cek Status Peserta BPJS" ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
        VClaim — verifikasi kartu Peserta BPJS via No Kartu / NIK
    </x-modul-dokumen.header>

    {{-- ═══════════ BODY ═══════════ --}}
    <div class="p-6 space-y-4">

        {{-- INPUT CARI --}}
        <form wire:submit.prevent="cekPeserta" class="space-y-2">
            <x-input-label for="noKartuBPJS" value="No Kartu BPJS (13 digit) atau NIK (16 digit)" />
            <div class="flex gap-2">
                <x-text-input id="noKartuBPJS" type="text" inputmode="numeric"
                    placeholder="Masukkan No Kartu BPJS / NIK" wire:model.defer="noKartuBPJS" autofocus class="flex-1"
                    wire:loading.attr="disabled" wire:target="cekPeserta" />
                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="cekPeserta">
                    <span wire:loading.remove wire:target="cekPeserta">Cek</span>
                    <span wire:loading wire:target="cekPeserta">Mencari...</span>
                </x-primary-button>
            </div>
            <p class="text-xs text-muted dark:text-gray-400">
                Tekan <b>Enter</b> untuk mencari peserta. Sistem auto-deteksi 13 digit = No Kartu, 16 digit = NIK.
            </p>
        </form>

        {{-- KARTU HASIL --}}
        @if (!empty($pesertaBPJS))
            @php
                $statusKeterangan = data_get($pesertaBPJS, 'statusPeserta.keterangan', '-');
                $isAktif = strtoupper($statusKeterangan) === 'AKTIF';
            @endphp
            <div class="p-4 border rounded-lg bg-surface-soft dark:bg-gray-800 dark:border-gray-700">

                {{-- IDENTITAS + STATUS --}}
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-lg font-semibold text-ink dark:text-gray-100">
                            {{ data_get($pesertaBPJS, 'nama', '-') }}
                        </div>
                        <div class="mt-1 text-sm text-muted dark:text-gray-300">
                            NIK: <span class="font-medium">{{ data_get($pesertaBPJS, 'nik', '-') }}</span>
                            &nbsp;•&nbsp;
                            No Kartu: <span class="font-medium">{{ data_get($pesertaBPJS, 'noKartu', '-') }}</span>
                        </div>
                        <div class="mt-1 text-sm text-muted dark:text-gray-300">
                            Lahir: <span class="font-medium">{{ data_get($pesertaBPJS, 'tglLahir', '-') }}</span>
                            &nbsp;•&nbsp;
                            Umur: <span
                                class="font-medium">{{ data_get($pesertaBPJS, 'umur.umurSekarang', '-') }}</span>
                            &nbsp;•&nbsp;
                            JK: <span class="font-medium">{{ data_get($pesertaBPJS, 'sex', '-') }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-muted dark:text-gray-400">Status</div>
                        <span
                            class="inline-flex px-3 py-1 mt-1 text-sm font-semibold rounded-full
                            {{ $isAktif ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }}">
                            {{ $statusKeterangan }}
                        </span>
                    </div>
                </div>

                {{-- DETAIL GRID --}}
                <div class="grid grid-cols-1 gap-3 mt-4 md:grid-cols-2">
                    <div class="p-3 bg-canvas border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                        <span class="block text-xs text-muted dark:text-gray-400">Jenis Peserta</span>
                        <span class="block font-medium text-ink dark:text-gray-100">
                            {{ data_get($pesertaBPJS, 'jenisPeserta.keterangan', '-') }}
                        </span>
                    </div>
                    <div class="p-3 bg-canvas border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                        <span class="block text-xs text-muted dark:text-gray-400">Hak Kelas</span>
                        <span class="block font-medium text-ink dark:text-gray-100">
                            {{ data_get($pesertaBPJS, 'hakKelas.keterangan', '-') }}
                        </span>
                    </div>
                    <div class="p-3 bg-canvas border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                        <span class="block text-xs text-muted dark:text-gray-400">Faskes Tk. 1</span>
                        <span class="block font-medium text-ink dark:text-gray-100">
                            {{ data_get($pesertaBPJS, 'provUmum.nmProvider', '-') }}
                        </span>
                    </div>
                    <div class="p-3 bg-canvas border rounded-lg dark:bg-gray-900 dark:border-gray-700">
                        <span class="block text-xs text-muted dark:text-gray-400">Masa Berlaku</span>
                        <span class="block text-sm text-body dark:text-gray-300">
                            TMT <span class="font-medium">{{ data_get($pesertaBPJS, 'tglTMT', '-') }}</span>
                            &nbsp;–&nbsp;
                            TAT <span class="font-medium">{{ data_get($pesertaBPJS, 'tglTAT', '-') }}</span>
                        </span>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-modal>
