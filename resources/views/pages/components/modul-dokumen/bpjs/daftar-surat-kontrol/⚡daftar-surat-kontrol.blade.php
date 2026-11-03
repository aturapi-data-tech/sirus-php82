<?php
// resources/views/pages/components/modul-dokumen/bpjs/daftar-surat-kontrol/daftar-surat-kontrol.blade.php
//
// Modal daftar surat kontrol BPJS milik kartu pasien (dibuka dari form SKDP RJ / RI) — hapus per baris di VClaim.
// Dipakai saat insert ditolak "Sudah Diterbitkan Rencana Kunjungan Kontrol Di Tanggal Yang Sama":
// petugas bisa melihat surat mana yang menempati tanggal itu lalu menghapusnya bila memang tak dipakai.
// Tampilan = standar modul dokumen (modal full, header, tabel-daftar, footer sticky).
// Hanya Admin & manager ke atas (Gate bpjs.hapusSuratKontrol) — dicek di buka & hapus, bukan cuma tombol.
// Fungsi dasarnya: membuat ulang No SKDP saat surat kontrol sudah terbit di BPJS tapi nomornya GAGAL tersimpan ke DB.
// Maka No SKDP BPJS yang SUDAH tersimpan di JSON kunjungan ini tidak boleh dihapus — dicek ulang dari DB saat hapus.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRJTrait, EmrRITrait;

    public string $noKartu = '';
    public string $noSepSekarang = '';
    public ?string $jalur = null;

    /** rjNo (RJ) / riHdrNo (RI) kunjungan pembuka modal. */
    public string $nomorKunjungan = '';

    /** No SKDP BPJS yang tersimpan di JSON kunjungan ini saat daftar dimuat (untuk tampilan; hapus() cek ulang dari DB). */
    public string $noSKDPBPJSTersimpan = '';

    /** Baris surat kontrol (jnsKontrol 2) dari BPJS — sedikit, aman sebagai properti publik. */
    public array $rows = [];

    public string $pesanGagalMuat = '';

    #[On('daftar-surat-kontrol-bpjs.open')]
    public function bukaDaftar(?string $noKartu = null, ?string $noSep = null, ?string $jalur = null, ?string $nomorKunjungan = null): void
    {
        if (!auth()->user()?->can('bpjs.hapusSuratKontrol')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin & manager ke atas yang dapat membuka/menghapus surat kontrol BPJS.');
            return;
        }
        if (!in_array($jalur, ['RJ', 'RI'], true) || blank($nomorKunjungan)) {
            $this->dispatch('toast', type: 'error', message: 'Kunjungan asal tidak dikenali — buka ulang dari form Surat Kontrol.');
            return;
        }
        $this->noKartu = trim((string) $noKartu);
        $this->noSepSekarang = trim((string) $noSep);
        $this->jalur = $jalur;
        $this->nomorKunjungan = trim((string) $nomorKunjungan);

        if ($this->noKartu === '') {
            $this->dispatch('toast', type: 'error', message: 'No kartu BPJS pasien tidak ditemukan.');
            return;
        }

        $this->muatDaftar();
        $this->dispatch('open-modal', name: 'daftar-surat-kontrol-bpjs');
    }

    /** No SKDP BPJS di JSON kunjungan ini, dibaca SEGAR dari DB. Jalur di luar RJ/RI → '' (bukaDaftar sudah menolak). */
    private function bacaNoSKDPBPJSTersimpan(): string
    {
        $data = [];
        if ($this->jalur === 'RJ') {
            $data = $this->findDataRJ($this->nomorKunjungan) ?? [];
        }
        if ($this->jalur === 'RI') {
            $data = $this->findDataRI($this->nomorKunjungan) ?? [];
        }

        return trim((string) ($data['kontrol']['noSKDPBPJS'] ?? ''));
    }

    /**
     * Daftar dari BPJS per bulan: rencana kontrol bulan lalu s/d 2 bulan ke depan (filter 2) + terbit bulan ini
     * (filter 1). Digabung per noSuratKontrol, hanya jnsKontrol "2" (surat kontrol; SPRI tidak ditampilkan).
     */
    private function muatDaftar(): void
    {
        $this->rows = [];
        $this->pesanGagalMuat = '';
        $this->noSKDPBPJSTersimpan = $this->bacaNoSKDPBPJSTersimpan();

        $sekarang = Carbon::now(config('app.timezone'))->startOfMonth();
        $periode = [
            [$sekarang->copy()->subMonth(), 2],
            [$sekarang->copy(), 2],
            [$sekarang->copy()->addMonth(), 2],
            [$sekarang->copy()->addMonths(2), 2],
            [$sekarang->copy(), 1],
        ];

        $rowsPerNomor = [];
        foreach ($periode as [$bulan, $filter]) {
            try {
                $response = VclaimTrait::suratkontrol_list_kartu($bulan->format('m'), $bulan->format('Y'), $this->noKartu, $filter)->getOriginalContent();
            } catch (\Throwable $e) {
                $this->pesanGagalMuat = 'Gagal menghubungi BPJS: ' . $e->getMessage();
                break;
            }
            $kodeResponse = (int) ($response['metadata']['code'] ?? 0);
            if ($kodeResponse === 408) {
                $this->pesanGagalMuat = 'BPJS tidak merespons (408). Coba muat ulang.';
                break;
            }
            if ($kodeResponse !== 200) {
                continue; // 201 "data tidak ditemukan" untuk bulan itu
            }

            foreach ((array) ($response['response']['list'] ?? []) as $row) {
                $noSuratKontrol = (string) ($row['noSuratKontrol'] ?? '');
                if ($noSuratKontrol === '' || (string) ($row['jnsKontrol'] ?? '') !== '2') {
                    continue;
                }
                $rowsPerNomor[$noSuratKontrol] = [
                    'noSuratKontrol' => $noSuratKontrol,
                    'tglRencanaKontrol' => substr((string) ($row['tglRencanaKontrol'] ?? ''), 0, 10),
                    'tglTerbitKontrol' => substr((string) ($row['tglTerbitKontrol'] ?? ''), 0, 10),
                    'namaPoliTujuan' => (string) ($row['namaPoliTujuan'] ?? ''),
                    'namaDokter' => (string) ($row['namaDokter'] ?? ''),
                    'noSepAsalKontrol' => (string) ($row['noSepAsalKontrol'] ?? ''),
                    'terbitSEP' => (string) ($row['terbitSEP'] ?? ''),
                    // Sudah dipakai menerbitkan SEP → BPJS menolak hapus → tombol hapus tidak ditampilkan.
                    'sudahTerbitSep' => strcasecmp(trim((string) ($row['terbitSEP'] ?? '')), 'Sudah') === 0,
                ];
            }
        }

        // Urut terbaru di atas (tanggal rencana kontrol).
        $this->rows = collect($rowsPerNomor)->sortByDesc('tglRencanaKontrol')->values()->all();
    }

    public function muatUlang(): void
    {
        $this->muatDaftar();
    }

    public function hapus(string $noSuratKontrol): void
    {
        if (!auth()->user()?->can('bpjs.hapusSuratKontrol')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin & manager ke atas yang dapat membuka/menghapus surat kontrol BPJS.');
            return;
        }

        // Hanya nomor yang memang tampil di daftar & belum dipakai terbit SEP (argumen dari klien tak dipercaya mentah).
        $row = collect($this->rows)->firstWhere('noSuratKontrol', $noSuratKontrol);
        if ($row === null || $row['sudahTerbitSep']) {
            $this->dispatch('toast', type: 'error', message: "Surat kontrol {$noSuratKontrol} tidak bisa dihapus.");
            return;
        }

        // Pengaman: No SKDP BPJS yang SUDAH tersimpan di kunjungan ini = surat kontrol yang sah → jangan dihapus.
        // Dibaca ulang dari DB (bukan dari tampilan) supaya nomor yang baru disimpan petugas lain ikut terlindungi.
        $noSKDPBPJSTersimpan = $this->bacaNoSKDPBPJSTersimpan();
        if ($noSKDPBPJSTersimpan !== '' && $noSKDPBPJSTersimpan === $noSuratKontrol) {
            $this->noSKDPBPJSTersimpan = $noSKDPBPJSTersimpan;
            $this->dispatch('toast', type: 'warning', message: "Surat kontrol {$noSuratKontrol} adalah No SKDP BPJS yang SUDAH tersimpan di kunjungan ini — hapus dibatalkan. Untuk mengubah tanggal/dokter, cukup simpan ulang form Surat Kontrol (Update).");
            return;
        }

        $response = VclaimTrait::suratkontrol_delete($noSuratKontrol)->getOriginalContent();
        $kodeResponse = (int) ($response['metadata']['code'] ?? 0);
        $pesanResponse = (string) ($response['metadata']['message'] ?? '');

        if ($kodeResponse === 200) {
            $this->dispatch('toast', type: 'success', message: "Surat kontrol {$noSuratKontrol} dihapus di BPJS.");
        } else {
            $this->dispatch('toast', type: 'error', message: "Hapus {$noSuratKontrol} gagal: {$kodeResponse} {$pesanResponse}");
        }

        $this->muatDaftar();
    }

    public function closeModal(): void
    {
        $this->rows = [];
        $this->pesanGagalMuat = '';
        $this->noSKDPBPJSTersimpan = '';
        $this->dispatch('close-modal', name: 'daftar-surat-kontrol-bpjs');
    }
};
?>

<div>
    <x-modal name="daftar-surat-kontrol-bpjs" size="full" height="full" focusable>
        <div class="flex flex-col min-h-full">
            <x-modul-dokumen.header judul="Daftar Surat Kontrol BPJS" :jalur="$jalur" :jumlah="count($rows)"
                ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                Surat kontrol milik kartu pasien di BPJS (rencana kontrol bulan lalu s/d 2 bulan ke depan). Hapus per baris
                untuk menghapusnya di VClaim — surat yang sudah dipakai menerbitkan SEP, atau yang sudah tersimpan sebagai
                No SKDP BPJS kunjungan ini, tidak bisa dihapus.
            </x-modul-dokumen.header>

            <div class="flex-1 p-4 sm:p-6 space-y-4">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-muted dark:text-gray-400">
                    <span>No Kartu: <span class="font-mono text-ink dark:text-gray-200">{{ $noKartu ?: '-' }}</span></span>
                    <span>SEP kunjungan ini: <span class="font-mono text-ink dark:text-gray-200">{{ $noSepSekarang ?: '-' }}</span></span>
                </div>

                @if ($pesanGagalMuat !== '')
                    <div class="px-4 py-2.5 text-sm text-red-700 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                        {{ $pesanGagalMuat }}
                    </div>
                @endif

                <x-modul-dokumen.tabel-daftar :kolom="['Tgl Rencana', 'No Surat Kontrol', 'Poli / Dokter', 'SEP Asal', 'Terbit SEP' => 'text-center', 'Aksi' => 'text-center w-24']">
                    @forelse ($rows as $row)
                        <tbody wire:key="surat-kontrol-{{ $row['noSuratKontrol'] }}" class="border-b border-hairline dark:border-gray-700">
                            <tr class="align-top hover:bg-surface-soft dark:hover:bg-gray-800/60">
                                <td class="px-4 py-3 font-mono whitespace-nowrap align-middle text-muted dark:text-gray-300">
                                    {{ $row['tglRencanaKontrol'] ? Carbon::parse($row['tglRencanaKontrol'])->format('d/m/Y') : '-' }}
                                    @if ($row['tglTerbitKontrol'])
                                        <span class="block text-xs">terbit {{ Carbon::parse($row['tglTerbitKontrol'])->format('d/m/Y') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono font-medium align-middle text-ink dark:text-white">
                                    {{ $row['noSuratKontrol'] }}
                                    @if ($noSKDPBPJSTersimpan !== '' && $row['noSuratKontrol'] === $noSKDPBPJSTersimpan)
                                        <x-badge variant="success" class="block mt-1 font-sans w-fit">Tersimpan di kunjungan ini</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-middle text-ink dark:text-gray-200">
                                    {{ $row['namaPoliTujuan'] ?: '-' }}
                                    <span class="block text-xs text-muted dark:text-gray-400">{{ $row['namaDokter'] }}</span>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <span class="font-mono text-ink dark:text-gray-200">{{ $row['noSepAsalKontrol'] ?: '-' }}</span>
                                    @if ($noSepSekarang !== '' && $row['noSepAsalKontrol'] === $noSepSekarang)
                                        <x-badge variant="success" class="ml-1">SEP ini</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center align-middle">
                                    <x-badge :variant="$row['sudahTerbitSep'] ? 'info' : 'warning'">{{ $row['terbitSEP'] ?: '-' }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-center align-middle whitespace-nowrap">
                                    @if ($row['sudahTerbitSep'] || ($noSKDPBPJSTersimpan !== '' && $row['noSuratKontrol'] === $noSKDPBPJSTersimpan))
                                        <span class="text-xs text-muted-soft dark:text-gray-500">-</span>
                                    @else
                                        <x-hapus-button action="hapus('{{ $row['noSuratKontrol'] }}')" title="Hapus Surat Kontrol di BPJS"
                                            message="Surat kontrol {{ $row['noSuratKontrol'] }} akan DIHAPUS di VClaim BPJS dan tidak bisa dikembalikan. Lanjutkan?"
                                            wire:key="hapus-surat-kontrol-{{ $row['noSuratKontrol'] }}" />
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    @empty
                        <x-modul-dokumen.baris-kosong :kolom="6" pesan="Belum ada surat kontrol untuk kartu ini pada periode tersebut." />
                    @endforelse
                </x-modul-dokumen.tabel-daftar>
            </div>

            {{-- Footer sticky (selaras x-modul-dokumen.footer layar daftar; tanpa Isi Formulir Baru) --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <p class="flex items-center gap-1.5 mr-auto text-sm text-muted dark:text-gray-400">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Satu kartu hanya boleh punya <strong>satu rencana kontrol per tanggal</strong>. Hapus yang tidak dipakai, lalu simpan ulang SKDP.</span>
                    </p>
                    <x-secondary-button type="button" wire:click="muatUlang" wire:loading.attr="disabled" wire:target="muatUlang">
                        <span wire:loading.remove wire:target="muatUlang" class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Muat Ulang
                        </span>
                        <span wire:loading wire:target="muatUlang" class="inline-flex items-center gap-2"><x-loading /> Memuat...</span>
                    </x-secondary-button>
                    <x-secondary-button type="button" wire:click="closeModal">Tutup</x-secondary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
