<?php
// Cek Jumlah Record & Reset per tab Administrasi RI — khusus manager ke atas (Gate ri.resetAdministrasi).
// Lahir dari kunjungan yang tab Lain-Lain-nya berisi ratusan ribu baris (paket ber-Qty salah ketik) sehingga
// modal Administrasi RI kehabisan memori saat dibuka — reset di sini tidak perlu membuka modal itu.
// Trigger dari daftar-ri: dispatch 'daftar-ri.cek-jumlah-record.open' dengan riHdrNo.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\Txn\Ri\EmrRITrait;

new class extends Component {
    use EmrRITrait;

    /*
     * Tabel yang dibaca tab-tab Administrasi RI, semuanya ber-kolom rihdr_no.
     * bisaReset = tab itu punya tombol Hapus per baris di Administrasi RI. Lab, Radiologi, Trf UGD/RJ,
     * OK & Bon Resep tidak — datanya milik modul asalnya, jadi di sini hanya dihitung.
     * Reset Jasa Medis/Dokter hanya menghapus baris jasanya; paket lain-lain & obatnya di-reset di tab masing-masing.
     */
    private const TAB_ADMINISTRASI_RI = [
        'rstxn_rivisits'      => ['label' => 'Visit', 'bisaReset' => true],
        'rstxn_rikonsuls'     => ['label' => 'Konsul', 'bisaReset' => true],
        'rstxn_riactparams'   => ['label' => 'Jasa Medis', 'bisaReset' => true],
        'rstxn_riactdocs'     => ['label' => 'Jasa Dokter', 'bisaReset' => true],
        'rstxn_rilabs'        => ['label' => 'Laboratorium', 'bisaReset' => false],
        'rstxn_riradiologs'   => ['label' => 'Radiologi', 'bisaReset' => false],
        'rstxn_ritempadmins'  => ['label' => 'Trf UGD/RJ', 'bisaReset' => false],
        'rstxn_riothers'      => ['label' => 'Lain-Lain', 'bisaReset' => true],
        'rstxn_rioks'         => ['label' => 'Operasi (OK)', 'bisaReset' => false],
        'rsmst_trfrooms'      => ['label' => 'Kamar', 'bisaReset' => true],
        'rstxn_ribonobats'    => ['label' => 'Bon Resep', 'bisaReset' => false],
        'rstxn_riobatrtns'    => ['label' => 'Return Obat', 'bisaReset' => true],
        'rstxn_riobats'       => ['label' => 'Obat Pinjam', 'bisaReset' => true],
    ];

    public ?int $riHdrNo = null;
    public bool $isFormLocked = false;
    public array $jumlahPerTab = [];
    public int $jumlahLainLainTerjoin = 0;

    #[On('daftar-ri.cek-jumlah-record.open')]
    public function bukaCekJumlahRecord(?int $riHdrNo = null): void
    {
        if (!auth()->user()?->can('ri.resetAdministrasi')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya manager ke atas yang dapat membuka Cek Jumlah Record.');
            return;
        }

        if (!$riHdrNo) {
            $this->dispatch('toast', type: 'error', message: 'No. RI tidak valid.');
            return;
        }

        $this->riHdrNo = $riHdrNo;
        $this->hitungJumlahRecord();
        $this->dispatch('open-modal', name: 'ri-cek-jumlah-record');
    }

    private function hitungJumlahRecord(): void
    {
        $this->isFormLocked = $this->checkRIStatus($this->riHdrNo);

        $this->jumlahPerTab = [];
        foreach (self::TAB_ADMINISTRASI_RI as $tabel => $tab) {
            try {
                $jumlah = DB::table($tabel)->where('rihdr_no', $this->riHdrNo)->count();
            } catch (\Throwable $e) {
                $jumlah = null;
            }
            $this->jumlahPerTab[] = ['tabel' => $tabel, 'label' => $tab['label'], 'bisaReset' => $tab['bisaReset'], 'jumlah' => $jumlah];
        }

        // Jumlah baris yang benar-benar dirender tab Lain-Lain (JOIN ke master, sama dengan findData()).
        $this->jumlahLainLainTerjoin = DB::table('rstxn_riothers')
            ->join('rsmst_others', 'rstxn_riothers.other_id', '=', 'rsmst_others.other_id')
            ->where('rstxn_riothers.rihdr_no', $this->riHdrNo)
            ->count();
    }

    public function resetTab(string $tabel): void
    {
        if (!auth()->user()?->can('ri.resetAdministrasi')) {
            $this->dispatch('toast', type: 'error', message: 'Hanya manager ke atas yang dapat me-reset tab Administrasi.');
            return;
        }

        if (!$this->riHdrNo) {
            return;
        }

        // Whitelist — nama tabel datang dari klien, jangan pernah dipakai langsung.
        $tab = self::TAB_ADMINISTRASI_RI[$tabel] ?? null;
        if (!$tab || !$tab['bisaReset']) {
            $this->dispatch('toast', type: 'error', message: 'Tab ini tidak bisa di-reset.');
            return;
        }

        if ($this->checkRIStatus($this->riHdrNo)) {
            $this->isFormLocked = true;
            $this->dispatch('toast', type: 'error', message: 'Pasien sudah pulang, transaksi terkunci.');
            return;
        }

        try {
            $jumlahTerhapus = DB::transaction(function () use ($tabel, $tab) {
                $this->lockRIRow($this->riHdrNo);

                $query = DB::table($tabel)->where('rihdr_no', $this->riHdrNo);
                // Return yang sudah dikirim ke Apotek tidak boleh hilang — stoknya sudah pindah (sama dengan removeRtnObat).
                if ($tabel === 'rstxn_riobatrtns') {
                    $query->whereNull('trf_no_apotek');
                }
                $jumlah = $query->delete();

                $this->appendAdminLogRI($this->riHdrNo, "Reset {$tab['label']}: {$jumlah} baris dihapus");

                return $jumlah;
            });

            $this->hitungJumlahRecord();
            $this->dispatch('toast', type: 'success', message: "Reset {$tab['label']}: " . number_format($jumlahTerhapus) . ' baris dihapus.');
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal reset: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->riHdrNo = null;
        $this->jumlahPerTab = [];
        $this->jumlahLainLainTerjoin = 0;
        $this->dispatch('close-modal', name: 'ri-cek-jumlah-record');
    }
};
?>

<div>
    <x-modal name="ri-cek-jumlah-record" size="2xl" focusable>
        @if ($riHdrNo)
            <x-modul-dokumen.header judul="Cek Jumlah Record — RI {{ $riHdrNo }}" jalur="RI" :readOnly="$isFormLocked"
                ikon="M4 7v10c0 2 3.6 4 8 4s8-2 8-4V7M4 7c0 2 3.6 4 8 4s8-2 8-4M4 7c0-2 3.6-4 8-4s8 2 8 4m-16 5c0 2 3.6 4 8 4s8-2 8-4">
                Jumlah record tiap tab Administrasi RI. Reset menghapus SEMUA baris tab itu untuk kunjungan ini.
            </x-modul-dokumen.header>

            <div class="px-6 py-4">
                <table class="w-full text-sm text-left">
                    <thead class="text-sm font-semibold text-gray-600 uppercase dark:text-gray-300 bg-surface-soft dark:bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-2">Tab</th>
                            <th class="px-4 py-2 text-right">Jumlah</th>
                            <th class="w-32 px-4 py-2 text-center">Reset</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline-soft dark:divide-gray-800">
                        @foreach ($jumlahPerTab as $row)
                            <tr wire:key="cek-jumlah-{{ $row['tabel'] }}">
                                <td class="px-4 py-1.5 text-ink dark:text-gray-200">
                                    {{ $row['label'] }}
                                    <span class="block font-mono text-xs text-muted dark:text-gray-400">{{ $row['tabel'] }}</span>
                                </td>
                                <td class="px-4 py-1.5 font-semibold text-right {{ ($row['jumlah'] ?? 0) > 1000 ? 'text-red-600' : 'text-ink dark:text-gray-200' }}">
                                    {{ $row['jumlah'] === null ? 'gagal dihitung' : number_format($row['jumlah']) }}
                                </td>
                                <td class="px-4 py-1.5 text-center">
                                    @if ($row['bisaReset'] && !$isFormLocked && ($row['jumlah'] ?? 0) > 0)
                                        <x-confirm-button variant="danger-soft" action="resetTab('{{ $row['tabel'] }}')"
                                            title="Reset {{ $row['label'] }}"
                                            message="Semua {{ number_format($row['jumlah']) }} baris {{ $row['label'] }} kunjungan ini akan dihapus. Lanjutkan?"
                                            confirmText="Ya, Reset" wire:key="reset-{{ $row['tabel'] }}-{{ $row['jumlah'] }}">
                                            Reset
                                        </x-confirm-button>
                                    @else
                                        <span class="text-xs text-muted-soft dark:text-gray-500">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td class="px-4 py-1.5 text-ink dark:text-gray-200">
                                Lain-Lain setelah JOIN master
                                <span class="block font-mono text-xs text-muted dark:text-gray-400">riothers ⋈ rsmst_others</span>
                            </td>
                            <td class="px-4 py-1.5 font-semibold text-right {{ $jumlahLainLainTerjoin > 1000 ? 'text-red-600' : 'text-ink dark:text-gray-200' }}">
                                {{ number_format($jumlahLainLainTerjoin) }}
                            </td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </x-modal>
</div>
