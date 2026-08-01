<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;
use App\Http\Traits\Txn\Penunjang\KamarOperasiTrait;
use App\Support\KamarOperasiTarif;

/**
 * Shell modal transaksi Kamar Operasi.
 *
 * Dipecah per bagian mengikuti Administrasi RJ: shell ini hanya memegang
 * identitas, total, status, dan aksi tingkat transaksi (Hitung Tarif OK,
 * Kirim ke Tagihan, Batal Transaksi). Isi tiap bagian ada di komponen anaknya:
 *
 *   crew-jasa-kamar-operasi   — crew + pos tarif + jasa on call
 *   tindakan-kamar-operasi    — tab Tindakan Operasi
 *   bahan-alat-kamar-operasi  — tab Bahan dan Alat
 *   omlop-kamar-operasi       — tab Crew OM LOP
 *
 * Kontraknya sama dengan Administrasi RJ: anak memuat datanya sendiri dari
 * `okReg`, lalu `dispatch('kamar-operasi.updated')` setelah menulis; shell dan
 * anak lain mendengar event itu untuk menyegarkan diri.
 */
new class extends Component {
    use WithRenderVersioningTrait, KamarOperasiTrait;

    public array $renderVersions = [];
    protected array $renderAreas = ['kamar-operasi-actions-modal'];

    public string $okReg = '';
    public string $activeTab = 'Tindakan'; // Tindakan | BahanAlat | Omlop
    public array $headerData = [];

    /** Sumber layanan kunjungan induk (RJ | UGD | RI) + nomornya. */
    public string $sumber = 'RI';
    public int $refNo = 0;

    public int $sumTotal = 0;
    public int $sumOncall = 0;

    /** Status 'A' = Proses Transaksi (bebas edit). Default TERKUNCI. */
    public bool $isFormLocked = true;

    /** Kunjungan rawat inap induk tidak aktif → transfer & batal tertutup. */
    public bool $indukTerkunci = false;
    public string $indukTerkunciSebab = '';

    public array $EmrMenuKamarOperasi = [['ermMenuId' => 'Tindakan', 'ermMenuName' => 'Tindakan Operasi'], ['ermMenuId' => 'BahanAlat', 'ermMenuName' => 'Bahan dan Alat'], ['ermMenuId' => 'Omlop', 'ermMenuName' => 'Crew OM LOP']];

    public function mount(): void
    {
        $this->registerAreas($this->renderAreas);
    }

    /* =======================
     | Open / Close
     * ======================= */
    #[On('kamar-operasi-actions.open')]
    public function openActions(string $okReg): void
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses ke modul Kamar Operasi.');
            return;
        }

        $this->okReg = $okReg;
        $this->activeTab = 'Tindakan';

        $this->findData();

        $this->incrementVersion('kamar-operasi-actions-modal');
        $this->dispatch('open-modal', name: 'kamar-operasi-actions');

        // Mode entry → kursor langsung ke pencarian Tindakan (tab pertama),
        // pola sama dengan Administrasi RJ.
        if (!$this->isFormLocked) {
            $this->dispatch('kamar-operasi-fokus', ke: 'ok-lov-tindakan');
        }
    }

    public function closeActions(): void
    {
        $this->dispatch('close-modal', name: 'kamar-operasi-actions');
        $this->reset(['okReg', 'headerData', 'sumber', 'refNo', 'sumTotal', 'sumOncall', 'isFormLocked', 'indukTerkunci', 'indukTerkunciSebab']);
    }

    /**
     * Anak melapor sesudah menulis. Selain menyegarkan total di shell, worklist
     * di belakang modal ikut diberi tahu — kalau tidak, kolom Total Tarif di sana
     * basi sampai modal ditutup.
     */
    #[On('kamar-operasi.updated')]
    public function onAnakUpdated(): void
    {
        $this->findData();
        $this->dispatch('refresh-after-kamar-operasi.saved');
    }

    /* =======================
     | LOAD
     * ======================= */
    public function findData(): void
    {
        if ($this->okReg === '') {
            return;
        }

        $kolomFee = array_keys(KamarOperasiTarif::POS);
        $kolomOncall = array_keys(KamarOperasiTarif::POS_ONCALL);

        ['sumber' => $this->sumber, 'refNo' => $this->refNo] = $this->sumberRefOk($this->okReg);

        // Kunjungan induk di-join sesuai sumber; `status_induk` diseragamkan
        // supaya sisa komponen tidak perlu tahu kolom aslinya (ri_status vs rj_status).
        //
        // Urutan join MENGIKAT: kunjungan dulu, baru rsmst_pasiens. Oracle
        // menyusun FROM sesuai urutan pemanggilan, jadi menaruh pasiens lebih
        // dulu membuat alias h belum dikenal → ORA-00904 "H"."REG_NO".
        $query = DB::table('rstxn_oks as o')
            ->select('o.ok_reg', 'o.status_rjri', 'o.ref_no', 'o.ok_status', DB::raw("to_char(o.ok_date,'dd/mm/yyyy hh24:mi:ss') as ok_date"), 'h.reg_no', ...$kolomFee, ...$kolomOncall)
            ->where('o.ok_reg', $this->okReg);

        if ($this->sumber === 'RI') {
            $query->join('rstxn_rihdrs as h', 'h.rihdr_no', '=', 'o.ref_no')->addSelect(DB::raw('h.ri_status as status_induk'));
        } else {
            $tabelInduk = $this->sumber === 'UGD' ? 'rstxn_ugdhdrs' : 'rstxn_rjhdrs';
            $query->join($tabelInduk . ' as h', 'h.rj_no', '=', 'o.ref_no')->addSelect(DB::raw('h.rj_status as status_induk'));
        }

        $header = $query->join('rsmst_pasiens as p', 'p.reg_no', '=', 'h.reg_no')->addSelect('p.reg_name')->first();

        $this->headerData = $header ? (array) $header : [];

        $this->sumTotal = KamarOperasiTarif::total($this->headerData);

        $this->sumOncall = 0;
        foreach ($kolomOncall as $kolom) {
            $this->sumOncall += (int) ($this->headerData[$kolom] ?? 0);
        }

        $this->isFormLocked = ($this->headerData['ok_status'] ?? 'A') !== 'A';

        $this->evaluasiIndukTerkunci();
    }

    /**
     * Transfer DAN batal sama-sama hanya sah selama kunjungan induk masih aktif
     * (RI `ri_status='I'`, RJ/UGD `rj_status='A'`) — keduanya menulis ke tagihan
     * yang sudah ditutup kalau kunjungannya selesai. Ini menyamai aturan Batal
     * Transfer UGD→RI.
     *
     * Yang disimpan hanya SEBAB-nya; kalimat lengkapnya disusun per aksi
     * (lihat pesanTerkunci) supaya tombol Batal tidak memakai kalimat transfer.
     */
    private function evaluasiIndukTerkunci(): void
    {
        $this->indukTerkunci = false;
        $this->indukTerkunciSebab = '';

        $statusInduk = strtoupper($this->headerData['status_induk'] ?? '');
        if ($this->indukAktifOk($this->sumber, $statusInduk)) {
            return;
        }

        $this->indukTerkunciSebab = $this->sebabIndukTerkunciOk($this->sumber, $statusInduk);
        $this->indukTerkunci = true;
    }

    /** Nama layanan tujuan transfer — dipakai di label tombol, banner, dan toast. */
    public function labelSumber(): string
    {
        return $this->labelSumberOk($this->sumber);
    }

    /** Kalimat lengkap sesuai aksi yang sedang tertutup. */
    public function pesanTerkunci(string $aksi): string
    {
        if (!$this->indukTerkunci) {
            return '';
        }

        return $this->indukTerkunciSebab . ' — ' . match ($aksi) {
            'transfer' => 'biaya tidak bisa ditransfer ke tagihan ' . $this->labelSumber() . '.',
            'batal' => 'transfer tidak bisa dibatalkan lagi.',
            default => 'transaksi terkunci.',
        };
    }

    /**
     * Pindah tab dari rantai Enter (kolom terakhir ditekan Enter dalam keadaan kosong).
     *
     * Harus lewat server, BUKAN sekadar mengubah variabel Alpine `tab`: Enter di
     * kolom angka memicu blur -> $wire.set, dan respons Livewire-nya me-morph DOM
     * sambil membawa activeTab lama sehingga perubahan sisi Alpine ketimpa balik.
     * Event `kamar-operasi-tab` dikirim SETELAH morph, jadi aman.
     */
    public function lanjutKeTab(string $tab): void
    {
        $tabBoleh = array_column($this->EmrMenuKamarOperasi, 'ermMenuId');

        if (!in_array($tab, $tabBoleh, true)) {
            return;
        }

        $this->activeTab = $tab;
        $this->dispatch('kamar-operasi-tab', ke: $tab);

        // Sekalian pindahkan kursor ke kotak cari tab tujuan — kalau tidak, tab
        // berganti tapi kursornya tertinggal di tab asal.
        $fokusPerTab = ['Tindakan' => 'ok-lov-tindakan', 'BahanAlat' => 'ok-lov-bahan', 'Omlop' => 'ok-lov-omlop'];
        $this->dispatch('kamar-operasi-fokus', ke: $fokusPerTab[$tab]);
    }

    /* =======================
     | HITUNG TARIF OK
     * ======================= */
    public function hitungTarifOk(): void
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return;
        }

        if ($this->isFormLocked) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi sudah selesai/dibatalkan — tarif tidak bisa dihitung ulang.');
            return;
        }

        $sumber = $this->sumber;
        $refNo = $this->refNo;
        $totalBaru = 0;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($sumber, $refNo, &$totalBaru) {
            $row = $this->kunciBarisOk($this->okReg);

            [, $totalBaru, $berubah] = KamarOperasiTarif::hitungUlang($this->okReg, $row);

            $ringkasan = $berubah === [] ? 'tidak ada pos yang berubah' : implode(', ', $berubah);
            $this->catatLogOk($sumber, $refNo, "Hitung Tarif OK No.{$this->okReg} — {$ringkasan}. Total Rp " . number_format($totalBaru));
        }, 'Gagal menghitung tarif');

        if (!$berhasil) {
            return;
        }

        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('refresh-after-kamar-operasi.saved');
        $this->dispatch('toast', type: 'success', message: 'Tarif OK dihitung ulang — total Rp ' . number_format($totalBaru) . '.');
    }

    /* =======================
     | TRANSFER BIAYA KE TAGIHAN KUNJUNGAN (A -> L)
     |
     | Tujuannya mengikuti sumber layanan: rstxn_rjoks / rstxn_ugdoks / rstxn_rioks.
     | Ketiganya tabel terpisah — bukan dititipkan ke pos Lain-Lain — supaya di
     | jurnal pendapatan operasi tidak menyamar sebagai pendapatan lain-lain.
     |
     | Beda dari form legacy: seluruh INSERT + UPDATE status dibungkus SATU
     | transaksi. Legacy melakukan COMMIT di tengah, sehingga kegagalan pada pos
     | ke-sekian meninggalkan biaya separuh yang tidak bisa dibatalkan.
     * ======================= */
    public function transferBiayaInap(): void
    {
        if (!$this->isAllowedRoleOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak memiliki akses.');
            return;
        }

        if (($this->headerData['ok_status'] ?? 'A') !== 'A') {
            $this->dispatch('toast', type: 'error', message: 'Data sudah diproses.');
            return;
        }

        $sumber = $this->sumber;
        $refNo = $this->refNo;

        if ($refNo <= 0) {
            $this->dispatch('toast', type: 'error', message: 'Transaksi ini tidak terkait kunjungan mana pun.');
            return;
        }

        $tabelBiaya = $this->tabelBiayaOk($sumber);
        $kolomInduk = $this->kolomIndukBiayaOk($sumber);
        $jumlahPos = 0;
        $totalTransfer = 0;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($sumber, $refNo, $tabelBiaya, $kolomInduk, &$jumlahPos, &$totalTransfer) {
            $jumlahPos = 0;
            $totalTransfer = 0;

            $row = $this->kunciBarisOk($this->okReg);

            $statusInduk = $this->kunciIndukOk($sumber, $refNo);
            if (!$this->indukAktifOk($sumber, $statusInduk)) {
                throw new \RuntimeException('Proses dibatalkan: ' . lcfirst($this->sebabIndukTerkunciOk($sumber, $statusInduk)) . '.');
            }

            // Oracle menolak FOR UPDATE pada query agregat (ORA-01786), jadi nomor
            // diambil MAX+1 seperti konvensi repo; tabrakan ditangani retry.
            $nomorBerikut = (int) DB::scalar("SELECT NVL(MAX(ok_no),0) FROM {$tabelBiaya}");

            foreach (KamarOperasiTarif::POS as $kolom => $keterangan) {
                $nilai = (int) ($row->{$kolom} ?? 0);
                if ($nilai <= 0) {
                    continue;
                }

                $nomorBerikut++;
                DB::table($tabelBiaya)->insert(['ok_no' => $nomorBerikut, 'ok_date' => $row->ok_date, 'ok_desc' => $keterangan, 'ok_price' => $nilai, $kolomInduk => $refNo, 'ok_reg' => $this->okReg]);

                $jumlahPos++;
                $totalTransfer += $nilai;
            }

            if ($jumlahPos === 0) {
                throw new \RuntimeException('Tidak ada tarif yang bisa ditransfer — hitung tarif OK terlebih dahulu.');
            }

            DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->update(['ok_status' => 'L']);

            $this->catatLogOk($sumber, $refNo, "Transfer biaya OK No.{$this->okReg} ke tagihan {$this->labelSumberOk($sumber)} — {$jumlahPos} pos, total Rp " . number_format($totalTransfer));
        }, 'Gagal transfer biaya');

        if (!$berhasil) {
            return;
        }

        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('refresh-after-kamar-operasi.saved');
        $this->dispatchAdministrasi();
        $regName = $this->headerData['reg_name'] ?? '';
        $this->dispatch('toast', type: 'success', message: "Biaya operasi pasien {$regName} berhasil ditransfer ke tagihan {$this->labelSumber()}.");
    }

    /** Beri tahu layar Administrasi yang sesuai supaya totalnya ikut segar. */
    private function dispatchAdministrasi(): void
    {
        $eventPerSumber = ['RJ' => 'administrasi-rj.updated', 'UGD' => 'administrasi-ugd.updated', 'RI' => 'administrasi-ri.updated'];

        if (isset($eventPerSumber[$this->sumber])) {
            $this->dispatch($eventPerSumber[$this->sumber]);
        }
    }

    /* =======================
     | BATAL TRANSAKSI (L -> A) — hapus baris biaya di rawat inap
     * ======================= */
    public function batalkanTransaksi(): void
    {
        if (!$this->isAllowedBatalOk()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak membatalkan transaksi ini.');
            return;
        }

        if (($this->headerData['ok_status'] ?? '') !== 'L') {
            $this->dispatch('toast', type: 'error', message: 'Transaksi tidak bisa dibatalkan dari status ini.');
            return;
        }

        $sumber = $this->sumber;
        $refNo = $this->refNo;
        $tabelBiaya = $this->tabelBiayaOk($sumber);
        $kolomInduk = $this->kolomIndukBiayaOk($sumber);
        $jumlahHapus = 0;

        $berhasil = $this->jalankanDenganRetryOk(function () use ($sumber, $refNo, $tabelBiaya, $kolomInduk, &$jumlahHapus) {
            $row = DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->lockForUpdate()->first();

            if (!$row) {
                throw new \RuntimeException('Transaksi tidak ditemukan.');
            }

            if (($row->ok_status ?? '') !== 'L') {
                throw new \RuntimeException('Status transaksi sudah berubah — silakan tutup dan buka ulang.');
            }

            if ($refNo > 0) {
                // Sama seperti Batal Transfer UGD→RI: begitu kunjungan induk tidak
                // aktif, pembatalan ikut tertutup — menghapus biaya dari tagihan yang
                // sudah ditutup membuat total kwitansi tidak lagi cocok.
                $statusInduk = $this->kunciIndukOk($sumber, $refNo);
                if (!$this->indukAktifOk($sumber, $statusInduk)) {
                    throw new \RuntimeException($this->sebabIndukTerkunciOk($sumber, $statusInduk) . ' — transfer tidak bisa dibatalkan lagi.');
                }

                $jumlahHapus = DB::table($tabelBiaya)->where('ok_reg', $this->okReg)->where($kolomInduk, $refNo)->delete();
            }

            DB::table('rstxn_oks')->where('ok_reg', $this->okReg)->update(['ok_status' => 'A']);

            $this->catatLogOk($sumber, $refNo, "Batal transfer biaya OK No.{$this->okReg} — {$jumlahHapus} baris biaya dihapus, status kembali Proses Transaksi");
        }, 'Gagal membatalkan transaksi');

        if (!$berhasil) {
            return;
        }

        $this->findData();
        $this->dispatch('kamar-operasi.updated');
        $this->dispatch('refresh-after-kamar-operasi.saved');
        $this->dispatchAdministrasi();
        $this->dispatch('toast', type: 'success', message: 'Pembatalan berhasil — status kembali ke Proses Transaksi.');
    }
};
?>

<div>
    <x-modal name="kamar-operasi-actions" size="full" height="full" focusable>
        <div class="flex flex-col h-full" wire:key="{{ $this->renderKey('kamar-operasi-actions-modal', [$okReg ?: 'empty']) }}">

            {{-- ═══════════ HEADER — display pasien + kartu total ═══════════ --}}
            <div class="relative px-6 py-4 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                @php
                    // Hanya KODE statusnya yang dipakai di sini (untuk gating tombol &
                    // kalimat terkunci). Teks + warna badge-nya urusan display-pasien.
                    $statusOk = $headerData['ok_status'] ?? 'A';
                @endphp

                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <livewire:pages::transaksi.penunjang.kamar-operasi.display-pasien-kamar-operasi.display-pasien-kamar-operasi
                            :okReg="$okReg" wire:key="display-pasien-kamar-operasi-{{ $okReg }}" />
                    </div>

                    {{-- Dua kartu total ditumpuk atas-bawah supaya header tidak melebar. --}}
                    <div class="flex flex-col self-end flex-shrink-0 gap-1.5 min-w-[190px]">
                        <div class="flex items-baseline justify-between gap-3 px-4 py-2 border rounded-2xl bg-brand-green/10 dark:bg-brand-lime/10 border-brand-green/20 dark:border-brand-lime/20">
                            <p class="text-xs font-medium tracking-wide uppercase text-brand-green dark:text-brand-lime whitespace-nowrap">
                                Tagihan Pasien
                            </p>
                            <p class="text-xl font-bold text-ink dark:text-white tabular-nums whitespace-nowrap">
                                Rp {{ number_format($sumTotal) }}
                            </p>
                        </div>
                        <div class="flex items-baseline justify-between gap-3 px-4 py-1.5 border rounded-2xl border-hairline bg-surface-soft dark:border-gray-700 dark:bg-gray-800/40">
                            <p class="text-xs font-medium tracking-wide uppercase text-muted whitespace-nowrap">
                                Jasa On Call
                                <span class="block text-xs normal-case text-muted-soft">tidak ditagihkan</span>
                            </p>
                            <p class="text-sm font-semibold text-ink dark:text-gray-200 tabular-nums whitespace-nowrap">
                                Rp {{ number_format($sumOncall) }}
                            </p>
                        </div>
                    </div>

                    {{-- Badge status TIDAK diulang di sini — display-pasien di kiri
                         sudah menampilkannya bersama layanan & status kunjungan. --}}
                    <div class="flex-shrink-0">
                        <x-icon-button color="gray" type="button" wire:click="closeActions">
                            <span class="sr-only">Tutup</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </x-icon-button>
                    </div>
                </div>
            </div>

            {{-- ═══════════ BODY ═══════════ --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20">
                <div class="max-w-full mx-auto space-y-4">

                    {{-- TRANSAKSI DIBATALKAN — didahulukan dari peringatan kunjungan.
                         Kalau tidak, spanduk kunjungan yang muncul dan berkata "tarif masih
                         boleh dilengkapi", padahal form dibatalkan terkunci seluruhnya. --}}
                    @if ($statusOk === 'F')
                        <div class="flex items-start gap-2 px-4 py-3 text-sm border rounded-xl border-error/30 bg-error/5 text-error dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            <div>
                                <p class="font-semibold">Transaksi operasi ini dibatalkan</p>
                                <p class="mt-1">
                                    Isinya ditampilkan apa adanya sebagai <span class="font-semibold">riwayat</span> —
                                    tindakan, tarif, dan crew tidak bisa diubah lagi, dan biayanya tidak masuk
                                    tagihan pasien.
                                </p>
                            </div>
                        </div>

                    {{-- PERINGATAN KUNJUNGAN RI TIDAK AKTIF --}}
                    @elseif ($indukTerkunci)
                        <div class="flex items-start gap-2 px-4 py-3 text-sm border rounded-xl border-warning/30 bg-warning-tint text-warning-deep dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
                            <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p class="font-semibold">{{ $indukTerkunciSebab }}</p>
                                @if ($statusOk === 'L')
                                    <p class="mt-1">
                                        Biaya operasi ini sudah masuk tagihan {{ $this->labelSumber() }} dan
                                        <span class="font-semibold">tidak bisa dibatalkan lagi</span> —
                                        membatalkan berarti menghapus biaya dari tagihan yang sudah ditutup.
                                    </p>
                                @else
                                    <p class="mt-1">
                                        Biaya <span class="font-semibold">tidak bisa ditransfer</span> ke tagihan
                                        {{ $this->labelSumber() }} karena kunjungannya sudah tidak aktif. Tarif masih
                                        boleh dilengkapi, tetapi tidak akan sampai ke tagihan pasien.
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Crew & Jasa bersebelahan dengan tab detail, 1 : 1. --}}
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">

                        <livewire:pages::transaksi.penunjang.kamar-operasi.crew-jasa-kamar-operasi
                            :okReg="$okReg" wire:key="crew-jasa-{{ $okReg }}" />

                        {{-- SUB-TAB DETAIL --}}
                        <div x-data="{ tab: @entangle('activeTab') }"
                            x-on:kamar-operasi-tab.window="
                                /* Lepas fokus dari field asal dulu; kalau tidak, penjaga
                                   anti-rebut di handler fokus melihat activeElement masih
                                   sebuah input dan membatalkan diri. Pola sama dengan
                                   administrasi-rj-goto-tab. */
                                document.activeElement?.blur();
                                tab = $event.detail.ke;
                            "
                            x-on:kamar-operasi-lanjut-tab.window="$wire.lanjutKeTab($event.detail.ke)"
                            x-on:kamar-operasi-fokus.window="
                                $nextTick(() => {
                                    const ke = $event.detail.ke;
                                    const fokus = () => {
                                        const wadah = document.getElementById(ke);
                                        if (!wadah) return;
                                        const el = wadah.matches('input, button') ? wadah : wadah.querySelector('input, button');
                                        if (!el || el === document.activeElement) return;
                                        // Jangan rebut kalau user sudah terlanjur mengetik di field lain.
                                        if (document.activeElement?.matches('input, select, textarea')) return;
                                        el.focus();
                                    };
                                    fokus();
                                    setTimeout(fokus, 150);
                                    setTimeout(fokus, 400);
                                })
                            "
                            class="overflow-hidden bg-canvas border border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">

                            <x-tabs variant="underline" class="flex-wrap p-2">
                                @foreach ($EmrMenuKamarOperasi as $menu)
                                    <x-tab active-expr="tab === '{{ $menu['ermMenuId'] }}'"
                                        x-on:click="tab = '{{ $menu['ermMenuId'] }}'">
                                        {{ $menu['ermMenuName'] }}
                                    </x-tab>
                                @endforeach
                            </x-tabs>

                            <div class="p-4 min-h-[240px]">
                                <div x-show="tab === 'Tindakan'" x-cloak>
                                    <livewire:pages::transaksi.penunjang.kamar-operasi.tindakan-kamar-operasi
                                        :okReg="$okReg" wire:key="tab-tindakan-{{ $okReg }}" />
                                </div>

                                <div x-show="tab === 'BahanAlat'" x-cloak>
                                    <livewire:pages::transaksi.penunjang.kamar-operasi.bahan-alat-kamar-operasi
                                        :okReg="$okReg" wire:key="tab-bahan-alat-{{ $okReg }}" />
                                </div>

                                <div x-show="tab === 'Omlop'" x-cloak>
                                    <livewire:pages::transaksi.penunjang.kamar-operasi.omlop-kamar-operasi
                                        :okReg="$okReg" wire:key="tab-omlop-{{ $okReg }}" />
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

            {{-- ═══════════ FOOTER ═══════════ --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                @php
                    // Kalimat disusun per aksi: tombol Batal tidak boleh memakai
                    // alasan yang ditulis untuk transfer, dan sebaliknya.
                    $pesanTerkunci = $this->pesanTerkunci($statusOk === 'L' ? 'batal' : 'transfer');
                @endphp

                <div class="flex flex-wrap items-center justify-between gap-3">

                    {{-- KIRI: Batal Transaksi (Admin / Supervisor Penunjang) --}}
                    <div class="flex items-center gap-2">
                        @if ($statusOk === 'L')
                            @hasanyrole(['Admin', 'Supervisor Penunjang'])
                                <span title="{{ $pesanTerkunci }}">
                                    <x-confirm-button variant="danger" action="batalkanTransaksi()"
                                        :disabled="$indukTerkunci" title="Batalkan Transaksi"
                                        :message="'Batalkan transfer biaya operasi ini? Seluruh baris biaya di tagihan ' . $this->labelSumber() . ' akan dihapus dan status kembali ke Proses Transaksi.'"
                                        confirmText="Ya, batalkan" cancelText="Batal" class="text-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        Batal Transaksi
                                    </x-confirm-button>
                                </span>
                            @endhasanyrole
                        @endif

                        @if ($indukTerkunci && $statusOk !== 'F')
                            <span class="inline-flex items-center gap-1 text-xs italic text-error dark:text-red-400">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                {{ $pesanTerkunci }}
                            </span>
                        @endif
                    </div>

                    {{-- KANAN: Aksi utama --}}
                    <div class="flex items-center gap-2">
                        @if ($statusOk === 'A')
                            <x-secondary-button type="button" wire:click="hitungTarifOk" wire:loading.attr="disabled"
                                wire:target="hitungTarifOk" class="text-xs">
                                <span wire:loading.remove wire:target="hitungTarifOk">Hitung Ulang Tarif</span>
                                <span wire:loading wire:target="hitungTarifOk" class="flex items-center gap-1.5">
                                    <x-loading /> Menghitung...
                                </span>
                            </x-secondary-button>

                            @php
                                // Tujuan transfer ikut sumber layanan. "Trf Biaya-INAP" adalah
                                // istilah form legacy — di layar ditulis apa adanya supaya petugas
                                // baru tidak perlu menebak singkatan "Trf".
                                $labelTombolTransfer = 'Kirim ke Tagihan ' . match ($sumber) {
                                    'RJ' => 'RJ',
                                    'UGD' => 'UGD',
                                    default => 'Rawat Inap',
                                };
                                $labelTujuanTransfer = $this->labelSumber();
                            @endphp

                            <span title="{{ $pesanTerkunci }}">
                                <x-confirm-button id="ok-tombol-transfer" variant="primary" action="transferBiayaInap()"
                                    :disabled="$indukTerkunci" :title="'Transfer Biaya ke ' . $labelTujuanTransfer"
                                    :message="'Transfer seluruh pos tarif operasi ini ke tagihan ' . $labelTujuanTransfer . '? Setelah ditransfer, tarif tidak bisa diubah lagi.'"
                                    confirmText="Ya, transfer" cancelText="Batal" class="text-xs">
                                    {{ $labelTombolTransfer }}
                                </x-confirm-button>
                            </span>
                        @elseif ($statusOk === 'L')
                            <x-badge variant="success">Biaya sudah masuk tagihan {{ $this->labelSumber() }}</x-badge>
                        @elseif ($statusOk === 'F')
                            {{-- Tanpa cabang ini footer transaksi dibatalkan kosong melompong:
                                 form mati tanpa satu pun keterangan kenapa. --}}
                            <x-badge variant="danger">Transaksi dibatalkan &mdash; hanya bisa dilihat</x-badge>
                        @endif

                        <x-secondary-button type="button" wire:click="closeActions">Tutup</x-secondary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
