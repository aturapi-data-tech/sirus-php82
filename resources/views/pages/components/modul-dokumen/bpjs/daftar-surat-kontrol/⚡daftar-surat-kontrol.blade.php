<?php
// resources/views/pages/components/modul-dokumen/bpjs/daftar-surat-kontrol/daftar-surat-kontrol.blade.php
//
// Modal daftar surat kontrol BPJS milik kartu pasien (dibuka dari form SKDP RJ / RI) — centang lalu hapus
// di VClaim. Dipakai saat insert ditolak "Sudah Diterbitkan Rencana Kunjungan Kontrol Di Tanggal Yang Sama":
// petugas bisa melihat surat mana yang menempati tanggal itu lalu menghapusnya bila memang tak dipakai.
// Hanya Admin & manager ke atas (Gate bpjs.hapusSuratKontrol) — dicek di buka & hapus, bukan cuma tombol.
// Setelah hapus, event 'surat-kontrol-bpjs.dihapus' dikirim agar form SKDP mengosongkan nomor yang ikut terhapus.

use Livewire\Component;
use Livewire\Attributes\On;
use Carbon\Carbon;
use App\Http\Traits\BPJS\VclaimTrait;

new class extends Component {
    public string $noKartu = '';
    public string $noSepSekarang = '';
    public ?string $jalur = null;

    /** Baris surat kontrol (jnsKontrol 2) dari BPJS — sedikit, aman sebagai properti publik. */
    public array $rows = [];

    /** noSuratKontrol yang dicentang. */
    public array $nomorTerpilih = [];

    public string $pesanGagalMuat = '';

    #[On('daftar-surat-kontrol-bpjs.open')]
    public function bukaDaftar(?string $noKartu = null, ?string $noSep = null, ?string $jalur = null): void
    {
        if (!auth()->user()?->can('bpjs.hapusSuratKontrol')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin & manager ke atas yang dapat membuka/menghapus surat kontrol BPJS.');
            return;
        }
        $this->noKartu = trim((string) $noKartu);
        $this->noSepSekarang = trim((string) $noSep);
        $this->jalur = $jalur;

        if ($this->noKartu === '') {
            $this->dispatch('toast', type: 'error', message: 'No kartu BPJS pasien tidak ditemukan.');
            return;
        }

        $this->muatDaftar();
        $this->dispatch('open-modal', name: 'daftar-surat-kontrol-bpjs');
    }

    /**
     * Daftar dari BPJS per bulan: rencana kontrol bulan lalu s/d 2 bulan ke depan (filter 2) + terbit bulan ini
     * (filter 1). Digabung per noSuratKontrol, hanya jnsKontrol "2" (surat kontrol; SPRI tidak ditampilkan).
     */
    private function muatDaftar(): void
    {
        $this->rows = [];
        $this->nomorTerpilih = [];
        $this->pesanGagalMuat = '';

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
                    // Sudah dipakai menerbitkan SEP → BPJS menolak hapus → tak bisa dicentang.
                    'sudahTerbitSep' => strcasecmp(trim((string) ($row['terbitSEP'] ?? '')), 'Sudah') === 0,
                ];
            }
        }

        $this->rows = collect($rowsPerNomor)->sortByDesc('tglRencanaKontrol')->values()->all();
    }

    public function muatUlang(): void
    {
        $this->muatDaftar();
    }

    public function hapusTerpilih(): void
    {
        if (!auth()->user()?->can('bpjs.hapusSuratKontrol')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Admin & manager ke atas yang dapat membuka/menghapus surat kontrol BPJS.');
            return;
        }
        // Hanya nomor yang memang tampil di daftar & belum dipakai terbit SEP (centang dari klien tak dipercaya mentah).
        $nomorBolehHapus = collect($this->rows)->reject(fn($row) => $row['sudahTerbitSep'])->pluck('noSuratKontrol')->all();
        $nomorTerpilih = array_values(array_intersect(array_map('strval', $this->nomorTerpilih), $nomorBolehHapus));
        if ($nomorTerpilih === []) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada surat kontrol yang dicentang.');
            return;
        }

        $nomorTerhapus = [];
        foreach ($nomorTerpilih as $noSuratKontrol) {
            $response = VclaimTrait::suratkontrol_delete($noSuratKontrol)->getOriginalContent();
            $kodeResponse = (int) ($response['metadata']['code'] ?? 0);
            $pesanResponse = (string) ($response['metadata']['message'] ?? '');

            if ($kodeResponse === 200) {
                $nomorTerhapus[] = $noSuratKontrol;
                $this->dispatch('toast', type: 'success', message: "Surat kontrol {$noSuratKontrol} dihapus di BPJS.");
            } else {
                $this->dispatch('toast', type: 'error', message: "Hapus {$noSuratKontrol} gagal: {$kodeResponse} {$pesanResponse}");
            }
        }

        if ($nomorTerhapus !== []) {
            $this->dispatch('surat-kontrol-bpjs.dihapus', nomorTerhapus: $nomorTerhapus);
        }

        $this->muatDaftar();
    }

    public function closeModal(): void
    {
        $this->rows = [];
        $this->nomorTerpilih = [];
        $this->pesanGagalMuat = '';
        $this->dispatch('close-modal', name: 'daftar-surat-kontrol-bpjs');
    }
};
?>

<div>
    <x-modal name="daftar-surat-kontrol-bpjs" size="4xl" focusable>
        <x-modul-dokumen.header judul="Daftar Surat Kontrol BPJS — Kartu {{ $noKartu }}" :jalur="$jalur"
            ikon="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
            Surat kontrol milik kartu pasien di BPJS (rencana kontrol bulan lalu s/d 2 bulan ke depan). Centang lalu
            Hapus untuk menghapusnya di VClaim — surat yang sudah dipakai menerbitkan SEP tidak bisa dihapus.
        </x-modul-dokumen.header>

        <div class="px-6 py-4 space-y-3">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm text-muted dark:text-gray-400">
                    SEP kunjungan ini: <span class="font-mono text-ink dark:text-gray-200">{{ $noSepSekarang ?: '-' }}</span>
                </p>
                <div class="flex items-center gap-2">
                    <x-secondary-button type="button" wire:click="muatUlang" wire:loading.attr="disabled" wire:target="muatUlang">
                        <span wire:loading.remove wire:target="muatUlang">Muat Ulang</span>
                        <span wire:loading wire:target="muatUlang" class="inline-flex items-center gap-2"><x-loading /> Memuat...</span>
                    </x-secondary-button>
                    @if (count($nomorTerpilih) > 0)
                        <x-confirm-button variant="danger" action="hapusTerpilih()"
                            title="Hapus Surat Kontrol di BPJS"
                            message="{{ count($nomorTerpilih) }} surat kontrol terpilih akan DIHAPUS di VClaim BPJS dan tidak bisa dikembalikan. Lanjutkan?"
                            confirmText="Ya, Hapus" wire:key="hapus-surat-kontrol-{{ implode('-', $nomorTerpilih) }}">
                            Hapus Terpilih ({{ count($nomorTerpilih) }})
                        </x-confirm-button>
                    @endif
                </div>
            </div>

            @if ($pesanGagalMuat !== '')
                <div class="px-4 py-2.5 text-sm text-red-700 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300">
                    {{ $pesanGagalMuat }}
                </div>
            @endif

            <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                <table class="w-full text-sm text-left">
                    <thead class="text-sm font-semibold text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                        <tr>
                            <th class="w-10 px-3 py-2"></th>
                            <th class="px-3 py-2">No Surat Kontrol</th>
                            <th class="px-3 py-2">Tgl Rencana</th>
                            <th class="px-3 py-2">Poli / Dokter</th>
                            <th class="px-3 py-2">SEP Asal</th>
                            <th class="px-3 py-2">Terbit SEP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                        @forelse ($rows as $row)
                            @php
                                $sepIni = $noSepSekarang !== '' && $row['noSepAsalKontrol'] === $noSepSekarang;
                            @endphp
                            <tr wire:key="surat-kontrol-{{ $row['noSuratKontrol'] }}" @class(['bg-red-50/60 dark:bg-red-900/10' => in_array($row['noSuratKontrol'], $nomorTerpilih, true)])>
                                <td class="px-3 py-2">
                                    <input type="checkbox" value="{{ $row['noSuratKontrol'] }}" wire:model.live="nomorTerpilih"
                                        @disabled($row['sudahTerbitSep'])
                                        class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-brand-green focus:ring-brand-green/50 focus:ring-2 dark:bg-gray-700 dark:border-gray-600 disabled:opacity-50" />
                                </td>
                                <td class="px-3 py-2 font-mono text-ink dark:text-gray-200">{{ $row['noSuratKontrol'] }}</td>
                                <td class="px-3 py-2 text-ink dark:text-gray-200">
                                    {{ $row['tglRencanaKontrol'] ? Carbon::parse($row['tglRencanaKontrol'])->format('d/m/Y') : '-' }}
                                    @if ($row['tglTerbitKontrol'])
                                        <span class="block text-xs text-muted dark:text-gray-400">terbit {{ Carbon::parse($row['tglTerbitKontrol'])->format('d/m/Y') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-ink dark:text-gray-200">
                                    {{ $row['namaPoliTujuan'] ?: '-' }}
                                    <span class="block text-xs text-muted dark:text-gray-400">{{ $row['namaDokter'] }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="font-mono text-ink dark:text-gray-200">{{ $row['noSepAsalKontrol'] ?: '-' }}</span>
                                    @if ($sepIni)
                                        <span class="block text-xs font-semibold text-brand-green dark:text-brand-lime">SEP ini</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-ink dark:text-gray-200">{{ $row['terbitSEP'] ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-6 text-center text-muted dark:text-gray-400">
                                    Tidak ada surat kontrol untuk kartu ini pada periode tersebut.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-modal>
</div>
