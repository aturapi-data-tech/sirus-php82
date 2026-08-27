<?php
// ╔══════════════════════════════════════════════════════════════════════╗
// ║  DAFTARKAN & KIRIM — modal klaim satu resep ke Apotek Online BPJS     ║
// ╚══════════════════════════════════════════════════════════════════════╝
//
// Dibuka dari worklist Apotek Online RJ. Merakit payload dari SEP + e-resep +
// kode_dpho lalu menjalankan rantai:
//   apotek_sep (baca POLIRSP/flagprb) → apotek_resep_insert (dapat No. SJP apotek)
//   → apotek_obat_nonracikan_insert / apotek_obat_racikan_insert (per obat)
//   → apotek_pelayanan_daftar (verifikasi) → simpan node apotekOnline ke JSON.
//
// SIGNA & JHO SENGAJA BISA DIEDIT: e-resep menyimpan signaX/signaHari/qty yang
// pemetaannya ke SIGNA1OBT×SIGNA2OBT dan JHO (jumlah hari obat) BPJS tidak pasti.
// Menebaknya diam-diam berbahaya (dosis salah = klaim salah), jadi field terisi
// tebakan terbaik dan petugas memverifikasi sebelum kirim — pola yang sama dengan
// LOV DPHO: pilih/periksa, jangan tebak.

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Traits\Txn\Rj\EmrRJTrait;
use App\Http\Traits\BPJS\ApotekTrait;
use App\Support\EresepJson;

new class extends Component {
    use EmrRJTrait, ApotekTrait;

    public ?string $rjNo = null;

    /** Ringkas pasien/SEP untuk header modal. */
    public array $info = [];

    /** Baris obat siap edit & kirim. */
    public array $obatList = [];

    /** Header resep — semua bisa diubah petugas. */
    public string $kdJnsObat = '1'; // 1 PRB · 2 Kronis · 3 Kemoterapi
    public string $iterasi = '0';   // 0 non-iterasi · 1 iterasi
    public string $noResep = '';

    /** Hasil kirim. */
    public string $noSjpApotek = '';
    public string $statusKlaim = 'draft'; // draft | terkirim
    public array $log = [];
    public bool $sedangKirim = false;

    /**
     * E-resep APA ADANYA dari dokter — ditampilkan berdampingan dengan payload klaim
     * supaya petugas bisa membandingkan: yang diresepkan vs yang diklaim ke BPJS.
     * Bentuknya hasil EresepJson::lembar() (RJ selalu 0 atau 1 lembar).
     * Isinya kecil (beberapa baris obat), aman ditaruh di properti publik.
     */
    public array $eresepLembar = [];

    /** Tab entri obat, meniru e-resep: NonRacikan | Racikan. */
    public string $tabObat = 'NonRacikan';


    /**
     * PERMINTAAN per grup racikan = berapa bungkus/puyer yang diminta. Disimpan
     * terpisah dari baris obat karena nilainya milik GRUP, bukan milik tiap bahan —
     * kalau ditaruh di baris, mengubah satu bahan bisa membuat grup jadi tak konsisten.
     * Bentuk: ['R1' => 10, 'R2' => 30]
     */
    public array $permintaanRacikan = [];

    #[On('apotek-online-rj.daftarkan')]
    public function open(string $rjNo): void
    {
        $this->reset(['obatList', 'log', 'noSjpApotek', 'sedangKirim', 'eresepLembar',
            'tabObat', 'permintaanRacikan']);
        $this->statusKlaim = 'draft';
        $this->rjNo = $rjNo;

        $data = $this->findDataRJ($rjNo);
        if (empty($data)) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->muatHeader($data);

        // E-resep dokter dimuat SELALU, terlepas dari draft/terkirim — ini rujukan
        // pembanding, bukan sumber payload.
        $this->eresepLembar = EresepJson::lembar($data);

        // RESTORE dari JSON bila sudah pernah disetup/dikirim — supaya yang tampil
        // adalah yang benar-benar disusun petugas (draft) atau yang dikirim, BUKAN
        // menghitung ulang dari rjobats. Kalau belum ada, baru bibit dari rjobats.
        $tersimpan = $data['apotekOnline'] ?? [];
        if (!empty($tersimpan['obat']) || !empty($tersimpan['obatRacikan'])) {
            $this->restoreDari($tersimpan);
        } else {
            // Bibit ditulis LANGSUNG ke JSON, bukan cuma ke memori: komponen anak
            // membaca daftarnya dari JSON, jadi kalau bibit hanya disimpan di induk
            // kedua tab akan tampil kosong pada kunjungan yang belum punya draft.
            $this->bibitDariKronis($data);
        }

        $this->segarkanDaftar();

        $this->dispatch('open-modal', name: 'apotek-online-rj-actions');
    }

    /** Muat header pasien/SEP saja (selalu segar dari DB). */
    private function muatHeader(array $data): void
    {
        $pasien = DB::table('rstxn_rjhdrs as h')
            ->join('rsmst_pasiens as p', 'h.reg_no', '=', 'p.reg_no')
            ->leftJoin('rsmst_polis as po', 'h.poli_id', '=', 'po.poli_id')
            ->leftJoin('rsmst_doctors as d', 'h.dr_id', '=', 'd.dr_id')
            ->where('h.rj_no', $this->rjNo)
            ->select('p.reg_name', 'h.reg_no', 'h.vno_sep', 'po.poli_desc', 'd.dr_name',
                DB::raw("to_char(h.rj_date,'yyyy-mm-dd hh24:mi:ss') as rj_date"), 'h.status_kronis', 'h.status_iter')
            ->first();

        $this->info = [
            'regName' => $pasien->reg_name ?? '-',
            'regNo' => $pasien->reg_no ?? '-',
            'noSep' => $pasien->vno_sep ?? '',
            'poliDesc' => $pasien->poli_desc ?? '-',
            'drName' => $pasien->dr_name ?? '-',
            'rjDate' => $pasien->rj_date ?? '',
            'statusKronisHdr' => $pasien->status_kronis ?? 'N',
            'statusIterHdr' => $pasien->status_iter ?? 'N',
        ];
    }

    /** Pulihkan setup yang tersimpan di JSON. */
    private function restoreDari(array $ao): void
    {
        $this->kdJnsObat = (string) ($ao['kdJnsObat'] ?? '1');
        $this->iterasi = (string) ($ao['iterasi'] ?? '0');
        $this->noResep = (string) ($ao['noResep'] ?? '');
        $this->noSjpApotek = (string) ($ao['noSjp'] ?? '');
        $this->statusKlaim = (string) ($ao['status'] ?? ($this->noSjpApotek !== '' ? 'terkirim' : 'draft'));
        // obatList & permintaanRacikan TIDAK diisi di sini — keduanya cermin yang
        // dibaca ulang oleh segarkanDaftar() supaya cuma ada satu jalur muat.
    }

    /** Bibit daftar obat dari obat KRONIS rjobats (dipakai bila belum ada draft). */
    private function bibitDariKronis(array $data): void
    {
        // Default jenis obat & iterasi dari penanda kronis yang sudah dicatat kasir.
        $this->kdJnsObat = ($this->info['statusKronisHdr'] ?? '') === 'Y' ? '2' : '1';
        $this->iterasi = ($this->info['statusIterHdr'] ?? '') === 'Y' ? '1' : '0';

        // SUMBER = rstxn_rjobats yang status_kronis='Y', BUKAN e-resep penuh.
        // Klaim Apotek Online hanya untuk porsi KRONIS obat (di luar paket INA-CBG),
        // dan jumlah yang diklaim adalah qty_kronis — bukan qty resep utuh. Obat
        // dalam paket (qty_bpjs) sudah ditagihkan lewat klaim INA-CBG, tak boleh
        // diklaim lagi di sini.
        $obatKronisList = DB::table('rstxn_rjobats as o')
            ->join('immst_products as p', 'o.product_id', '=', 'p.product_id')
            ->where('o.rj_no', $this->rjNo)
            ->where('o.status_kronis', 'Y')
            ->where('o.qty_kronis', '>', 0)
            ->select('o.product_id', 'p.product_name', 'p.kode_dpho',
                'o.qty_kronis', 'o.status_iter', 'o.iter_qty',
                'o.rj_carapakai', 'o.catatan_khusus')
            ->orderBy('o.rjobat_dtl')
            ->get();

        $obatBibit = [];
        foreach ($obatKronisList as $obatKronis) {
            $obatBibit[] = [
                'jenis' => 'nonRacikan',       // split kronis RJ hanya untuk non-racikan
                'noRacikan' => '',
                'productId' => (string) $obatKronis->product_id,
                'nama' => (string) ($obatKronis->product_name ?? '-'),
                'kodeDpho' => (string) ($obatKronis->kode_dpho ?? ''),
                // JMLOBT = porsi KRONIS. Signa & JHO tebakan awal — WAJIB diverifikasi
                // (e-resep/rjobats tak menyimpan signa dalam format BPJS yang baku).
                'signa1' => '1',
                'signa2' => '1',
                'jml' => (string) (int) $obatKronis->qty_kronis,
                'jho' => (string) (int) $obatKronis->qty_kronis,
                'catatan' => trim((string) ($obatKronis->catatan_khusus ?: $obatKronis->rj_carapakai)),
            ];
        }

        // No. resep apotek dari e-resep bila ada; fallback 5 digit dari No. RJ
        // (BPJS batasi 5 digit & unik per bulan — petugas boleh mengubah).
        $lembar = EresepJson::lembar($data);
        $noResep = (string) ($lembar[0]['resepNo'] ?? '');
        if ($noResep === '') {
            $noResep = substr(preg_replace('/\D/', '', (string) $this->rjNo), -5);
        }

        $this->noResep = $noResep;

        if (!$obatBibit) {
            return;
        }

        DB::transaction(function () use ($obatBibit) {
            $this->lockRJRow($this->rjNo);

            $segar = $this->findDataRJ($this->rjNo);
            $segar['apotekOnline'] ??= [];
            $segar['apotekOnline']['obat'] = $obatBibit;
            $segar['apotekOnline']['status'] ??= 'draft';
            $segar['apotekOnline']['noResep'] ??= $this->noResep;

            $this->updateJsonRJ($this->rjNo, $segar);
        });
    }

    /* ── Edit daftar obat (seperti e-resep: hapus / tambah) ─────────────── */

    /**
     * Cermin daftar obat dari JSON. Entri sekarang dikelola dua komponen ANAK
     * (non racikan & racikan) yang masing-masing menulis node-nya sendiri; induk
     * cuma membaca ulang untuk menghitung "siap kirim" dan menyusun payload.
     *
     * Digabung jadi satu daftar karena rantai kirim memang memprosesnya berurutan
     * — cabang racikan/non-racikan ditentukan per baris lewat `jenis`.
     */
    #[On('apotek-online-rj.obat-berubah')]
    public function segarkanDaftar(): void
    {
        if (blank($this->rjNo)) {
            return;
        }

        $ao = $this->findDataRJ($this->rjNo)['apotekOnline'] ?? [];

        $this->obatList = array_merge(
            array_values(array_filter($ao['obat'] ?? [], 'is_array')),
            array_values(array_filter($ao['obatRacikan'] ?? [], 'is_array')),
        );
        $this->permintaanRacikan = array_filter(
            (array) ($ao['permintaanRacikan'] ?? []),
            fn($jumlah) => is_numeric($jumlah)
        );
    }

    /** Obat yang siap kirim = punya kode DPHO. */
    private function siap(): array
    {
        return array_values(array_filter($this->obatList, fn($obat) => trim($obat['kodeDpho']) !== ''));
    }

    public function jumlahSiap(): int { return count($this->siap()); }
    public function jumlahBelumDpho(): int { return count($this->obatList) - $this->jumlahSiap(); }

    /** Simpan sebagai DRAFT ke JSON — tanpa mengirim ke BPJS. */
    public function simpanDraf(): void
    {
        if ($this->statusKlaim === 'terkirim') {
            return;
        }
        $data = $this->findDataRJ($this->rjNo);
        $this->simpanNode($data, 'draft', $this->noSjpApotek, 0);
        $this->dispatch('toast', type: 'success', message: 'Draf klaim tersimpan.');
        $this->dispatch('apotek-online-rj.refresh');
    }

    /**
     * Jalankan rantai klaim. Berhenti di kegagalan pertama dengan pesan jelas —
     * karena resep_insert harus sukses sebelum obat bisa dikirim, dan tiap obat
     * memakai No. SJP dari langkah itu.
     */
    public function kirim(): void
    {
        $this->log = [];
        $this->sedangKirim = true;

        try {
            if (empty($this->info['noSep'])) {
                $this->gagal('SEP asal kosong — resep apotek tak bisa dibuat.');
                return;
            }
            if ($this->jumlahSiap() === 0) {
                $this->gagal('Tak ada obat yang siap: semua belum dipetakan ke kode DPHO di Master Obat.');
                return;
            }

            $data = $this->findDataRJ($this->rjNo);
            $tgl = Carbon::parse($this->info['rjDate'] ?? now());

            // 1) POLIRSP dari SEP (BPJS pakai kode poli-nya sendiri, bukan poli_id kita).
            $sep = $this->apotek_sep($this->info['noSep'])->getData(true);
            if ((string) ($sep['metadata']['code'] ?? '') !== '200') {
                $this->gagal('Baca SEP gagal — ' . ($sep['metadata']['message'] ?? 'gangguan BPJS'));
                return;
            }
            $poliRsp = (string) ($sep['response']['poli'] ?? '');
            $this->log[] = ['ok' => true, 'teks' => 'SEP terbaca. Poli resep: ' . ($poliRsp ?: '(kosong)')];

            // 2) Simpan resep → dapat No. SJP apotek.
            $noResep = (string) ($this->noResep ?: '1');
            $resep = $this->apotek_resep_insert([
                'TGLSJP' => $tgl->format('Y-m-d H:i:s'),
                'REFASALSJP' => $this->info['noSep'],
                'POLIRSP' => $poliRsp,
                'KDJNSOBAT' => $this->kdJnsObat,
                'NORESEP' => $noResep,
                'IDUSERSJP' => (string) (auth()->user()->myuser_code ?? 'SIMRS'),
                'TGLRSP' => $tgl->format('Y-m-d 00:00:00'),
                'TGLPELRSP' => now()->format('Y-m-d 00:00:00'),
                'KdDokter' => '0',
                'iterasi' => $this->iterasi,
            ])->getData(true);
            if ((string) ($resep['metadata']['code'] ?? '') !== '200') {
                $this->gagal('Simpan resep ditolak — ' . ($resep['metadata']['message'] ?? 'gangguan BPJS'));
                return;
            }
            $noSjp = (string) ($resep['response']['noApotik'] ?? '');
            if ($noSjp === '') {
                $this->gagal('Resep tersimpan tapi No. SJP apotek tidak dikembalikan BPJS.');
                return;
            }
            $this->noSjpApotek = $noSjp;
            $this->log[] = ['ok' => true, 'teks' => 'Resep terdaftar. No. SJP apotek: ' . $noSjp];

            // 3) Kirim tiap obat siap.
            $terkirim = 0;
            foreach ($this->siap() as $obat) {
                $dasar = [
                    'NOSJP' => $noSjp, 'NORESEP' => $noResep,
                    'KDOBT' => $obat['kodeDpho'], 'NMOBAT' => $obat['nama'],
                    'SIGNA1OBT' => (int) $obat['signa1'], 'SIGNA2OBT' => (int) $obat['signa2'],
                    'JMLOBT' => (int) $obat['jml'], 'JHO' => (int) $obat['jho'],
                    'CatKhsObt' => $obat['catatan'],
                ];

                if ($obat['jenis'] === 'racikan') {
                    $dasar['JNSROBT'] = $obat['noRacikan'];
                    // PERMINTAAN milik GRUP (berapa bungkus diminta), bukan per bahan.
                    $dasar['PERMINTAAN'] = max(1, (int) ($this->permintaanRacikan[$obat['noRacikan']] ?? 1));
                    $resp = $this->apotek_obat_racikan_insert($dasar)->getData(true);
                } else {
                    $resp = $this->apotek_obat_nonracikan_insert($dasar)->getData(true);
                }

                if ((string) ($resp['metadata']['code'] ?? '') === '200') {
                    $terkirim++;
                    $this->log[] = ['ok' => true, 'teks' => 'Obat terkirim: ' . $obat['nama']];
                } else {
                    $this->log[] = ['ok' => false, 'teks' => $obat['nama'] . ' — ' . ($resp['metadata']['message'] ?? 'ditolak')];
                }
            }

            // 4) Simpan node PENUH (header + daftar obat) ke JSON: jadi jejak klaim
            //    yang bisa ditinjau ulang, dan penanda "Terdaftar" di worklist.
            $this->statusKlaim = 'terkirim';
            $this->simpanNode($data, 'terkirim', $noSjp, $terkirim);
            $this->log[] = ['ok' => true, 'teks' => "Selesai. {$terkirim} obat terkirim."];

            $this->dispatch('toast', type: 'success', message: "Klaim terkirim. No. SJP: {$noSjp} ({$terkirim} obat).");
            $this->dispatch('apotek-online-rj.refresh');
        } catch (\Throwable $e) {
            $this->gagal('Kesalahan: ' . $e->getMessage());
        } finally {
            $this->sedangKirim = false;
        }
    }

    private function gagal(string $pesan): void
    {
        $this->log[] = ['ok' => false, 'teks' => $pesan];
        $this->dispatch('toast', type: 'error', message: $pesan);
        $this->sedangKirim = false;
    }

    /**
     * Tulis node apotekOnline PENUH ke JSON — header + DAFTAR OBAT yang disusun.
     * Menyimpan daftar obat, bukan cuma nomor SJP: supaya klaim bisa ditinjau
     * ulang persis seperti yang dikirim, dan draft petugas tak hilang saat modal
     * ditutup. Node lama tidak ditimpa membabi buta — waktu/petugas SUBMIT hanya
     * dicatat saat benar-benar terkirim.
     */
    private function simpanNode(array $data, string $status, string $noSjp, int $terkirim): void
    {
        DB::transaction(function () use ($status, $noSjp, $terkirim) {
            $this->lockRJRow($this->rjNo);
            $data = $this->findDataRJ($this->rjNo);
            $lama = $data['apotekOnline'] ?? [];

            $node = [
                'status' => $status,
                'noSjp' => $noSjp,
                'kdJnsObat' => $this->kdJnsObat,
                'iterasi' => $this->iterasi,
                'noResep' => $this->noResep,
                // obat & obatRacikan SENGAJA tidak ditulis di sini — keduanya milik
                // komponen anak yang sudah menyimpan tiap perubahan. Menimpanya dari
                // induk berarti membuang entri yang dibuat setelah modal dibuka.
                'obat' => array_values($lama['obat'] ?? []),
                'obatRacikan' => array_values($lama['obatRacikan'] ?? []),
                'permintaanRacikan' => (array) ($lama['permintaanRacikan'] ?? []),
                'ubahAt' => now(env('APP_TIMEZONE'))->format('Y-m-d H:i:s'),
                'ubahOleh' => (string) (auth()->user()->myuser_name ?? auth()->user()->name ?? '-'),
            ];

            if ($status === 'terkirim') {
                $node['jmlObatTerkirim'] = $terkirim;
                $node['kirimAt'] = now(env('APP_TIMEZONE'))->format('Y-m-d H:i:s');
                $node['kirimOleh'] = (string) (auth()->user()->myuser_name ?? auth()->user()->name ?? '-');
            } else {
                // Draft ulang tidak menghapus jejak kirim sebelumnya (kalau ada).
                foreach (['jmlObatTerkirim', 'kirimAt', 'kirimOleh'] as $kunci) {
                    if (isset($lama[$kunci])) $node[$kunci] = $lama[$kunci];
                }
            }

            $data['apotekOnline'] = $node;
            $this->updateJsonRJ($this->rjNo, $data);
        });
    }
};
?>

<div>
    <x-modal name="apotek-online-rj-actions" size="full" height="full" focusable>
        <div class="flex flex-col h-full">

            {{-- JUDUL + TOMBOL TUTUP SEBARIS — susunan baku modul dokumen (docs/modul-dokumen-ri-pattern.md §2a):
               | [ikon · judul · deskripsi · badge · X] satu baris, X anak TERAKHIR dengan ml-auto shrink-0.
               | Jangan dibuat mengambang (absolute), jangan diberi baris sendiri, jangan masuk kelompok judul. --}}
            <div class="relative px-6 py-2.5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>

                <div class="relative flex items-center gap-3 min-w-0">
                    <div class="flex items-center flex-1 gap-3 min-w-0">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="flex items-center justify-center w-7 h-7 rounded-lg shrink-0 bg-brand-green/10 dark:bg-brand-lime/15">
                                <svg class="w-4 h-4 text-brand-green dark:text-brand-lime" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>

                            <div class="flex items-baseline gap-2 min-w-0">
                                <h2 class="text-sm font-semibold truncate shrink-0 text-ink dark:text-gray-100">
                                    Daftarkan &amp; Kirim ke Apotek Online
                                </h2>
                                <p class="flex-1 min-w-0 text-xs truncate text-muted dark:text-gray-400">
                                    Klaim obat PRB, kronis, dan kemoterapi ke BPJS untuk kunjungan rawat jalan ini
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 ml-auto shrink-0">
                            <x-badge class="shrink-0 whitespace-nowrap" variant="info">Rawat Jalan</x-badge>
                            @if ($statusKlaim === 'terkirim')
                                <x-badge class="shrink-0 whitespace-nowrap" variant="success">Terkirim</x-badge>
                            @else
                                <x-badge class="shrink-0 whitespace-nowrap" variant="gray">Draf</x-badge>
                            @endif
                        </div>
                    </div>

                    <x-icon-button color="gray" type="button" class="ml-auto shrink-0"
                        x-on:click="$dispatch('close-modal', { name: 'apotek-online-rj-actions' })">
                        <span class="sr-only">Tutup</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- DISPLAY PASIEN — baris tersendiri di bawah judul, mengikuti pola modul dokumen.
               | wire:key MEMUAT $rjNo: ganti pasien = key berubah = mount ulang dgn rjNo baru.
               | Digerbangi @if supaya tidak me-mount (dan membaca CLOB) saat modal tertutup. --}}
            @if (filled($rjNo))
                <div class="px-4 pt-2">
                    <livewire:pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo="$rjNo"
                        wire:key="apotek-online-display-pasien-{{ $rjNo }}" />
                </div>
            @endif

            {{-- BODY --}}
            <div class="flex-1 px-6 py-5 overflow-y-auto bg-surface-soft/60 dark:bg-gray-950/20">
                <div class="grid grid-cols-1 gap-5 xl:grid-cols-5">

                {{-- ══════════ KIRI: E-RESEP DARI DOKTER (rujukan, tidak bisa diubah) ══════════
                   | Ditaruh berdampingan supaya petugas bisa membandingkan apa yang DIRESEPKAN
                   | dengan apa yang DIKLAIM. Payload klaim hanya memuat porsi kronis, jadi wajar
                   | kalau isinya lebih sedikit — penandanya centang hijau di kolom ini.
                   ══════════════════════════════════════════════════════════════════════════ --}}
                <div class="xl:col-span-2 space-y-4">

                    {{-- HEADER RESEP — dipindah ke kolom KIRI. Ketiganya berlaku untuk SELURUH
                       | klaim (bukan per obat), jadi tempatnya di sisi konteks bersama e-resep
                       | dokter; kolom kanan disisakan penuh untuk entri obat.
                       | Kolom kiri sempit, maka Jenis Obat sendirian dan dua sisanya berdampingan. --}}
                    <div class="p-3 border rounded-lg bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                        {{-- Satu baris, lebar dibagi menurut isi terpanjangnya:
                           | Jenis Obat 5/12 ("Obat Kronis Belum Stabil"), Iterasi 4/12
                           | ("Non-Iterasi"), No. Resep 3/12 (maksimal 5 karakter). --}}
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                            <div class="sm:col-span-5">
                                <x-input-label value="Jenis Obat (KDJNSOBAT)" />
                                <x-select-input wire:model="kdJnsObat" class="w-full mt-1">
                                    <option value="1">Obat PRB</option>
                                    <option value="2">Obat Kronis Belum Stabil</option>
                                    <option value="3">Obat Kemoterapi</option>
                                </x-select-input>
                            </div>
                            <div class="sm:col-span-4">
                                <x-input-label value="Iterasi" />
                                <x-select-input wire:model="iterasi" class="w-full mt-1">
                                    <option value="0">Non-Iterasi</option>
                                    <option value="1">Iterasi</option>
                                </x-select-input>
                            </div>
                            <div class="sm:col-span-3">
                                <x-input-label value="No. Resep (maks 5)" />
                                <x-text-input wire:model="noResep" maxlength="5" @disabled($statusKlaim === 'terkirim')
                                    class="w-full mt-1" />
                            </div>
                        </div>
                    </div>

                    @php $productIdDiklaim = array_values(array_filter(array_column($obatList, 'productId'))); @endphp

                    <div class="border rounded-lg bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex items-center justify-between px-3 py-2 border-b border-hairline dark:border-gray-700">
                            <h3 class="text-sm font-semibold text-ink dark:text-gray-100">E-Resep dari Dokter</h3>
                            <span class="text-xs text-muted dark:text-gray-400">{{ $info['drName'] ?? '-' }}</span>
                        </div>

                        @forelse ($eresepLembar as $lembar)
                            {{-- NON-RACIKAN — tabel TETAP tampil walau lembarnya hanya berisi
                               | racikan, supaya susunan kolomnya konsisten dengan tabel lain. --}}
                            <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-surface-card dark:bg-gray-800">
                                            <tr class="text-xs font-semibold text-left uppercase text-muted dark:text-gray-300">
                                                <th class="px-3 py-2">Obat</th>
                                                <th class="w-20 px-3 py-2">Signa</th>
                                                <th class="w-14 px-3 py-2 text-right">Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-hairline dark:divide-gray-700">
                                            @forelse ($lembar['nonRacikan'] as $obat)
                                                @php $ikutDiklaim = in_array((string) ($obat['productId'] ?? ''), $productIdDiklaim, true); @endphp
                                                <tr class="align-top">
                                                    <td class="px-3 py-2">
                                                        <div class="flex items-start gap-1.5">
                                                            @if ($ikutDiklaim)
                                                                <span class="mt-0.5 text-success" title="Ikut diklaim ke BPJS">✓</span>
                                                            @endif
                                                            <div>
                                                                <div class="text-ink dark:text-gray-100">{{ $obat['productName'] ?? '-' }}</div>
                                                                @if (filled($obat['catatanKhusus'] ?? null))
                                                                    <div class="text-xs text-muted dark:text-gray-400">{{ $obat['catatanKhusus'] }}</div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-2 text-muted dark:text-gray-400">
                                                        {{ $obat['signaX'] ?? '-' }} × {{ $obat['signaHari'] ?? '-' }}
                                                    </td>
                                                    <td class="px-3 py-2 text-right text-ink dark:text-gray-100">{{ $obat['qty'] ?? '-' }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="px-3 py-6 text-center text-muted">
                                                        Tidak ada obat non racikan.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                            {{-- RACIKAN: dikelompokkan per noRacikan. Klaim kronis RJ belum
                               | menangani racikan, jadi kolom ini murni informatif. --}}
                            @foreach ($lembar['racikan'] as $noRacikan => $daftarBahan)
                                <div class="px-3 py-2 border-t border-hairline dark:border-gray-700">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-semibold text-ink dark:text-gray-100">Racikan {{ $noRacikan }}</span>
                                        <x-badge variant="warning">tidak diklaim</x-badge>
                                    </div>
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach ($daftarBahan as $bahan)
                                            <li class="text-xs text-muted dark:text-gray-400">
                                                {{ $bahan['productName'] ?? '-' }}
                                                @if (filled($bahan['dosis'] ?? null)) · {{ $bahan['dosis'] }} @endif
                                                @if (filled($bahan['takar'] ?? null)) {{ $bahan['takar'] }} @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @php $bahanPertama = $daftarBahan[0] ?? []; @endphp
                                    <div class="mt-1 text-xs text-muted dark:text-gray-400">
                                        Signa {{ $bahanPertama['signaX'] ?? '-' }} × {{ $bahanPertama['signaHari'] ?? '-' }}
                                        @if (filled($bahanPertama['qty'] ?? null)) · {{ $bahanPertama['qty'] }} bungkus @endif
                                    </div>
                                </div>
                            @endforeach
                        @empty
                            {{-- Kosong pun tabel tetap tampil, seragam dengan tabel entri obat. --}}
                            <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-surface-card dark:bg-gray-800">
                                            <tr class="text-xs font-semibold text-left uppercase text-muted dark:text-gray-300">
                                                <th class="px-3 py-2">Obat</th>
                                                <th class="w-20 px-3 py-2">Signa</th>
                                                <th class="w-14 px-3 py-2 text-right">Qty</th>
                                            </tr>
                                        </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="3" class="px-3 py-8 text-center text-muted">
                                                Dokter belum menulis e-resep untuk kunjungan ini.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- ══════════ KANAN: PAYLOAD KLAIM KE BPJS ══════════ --}}
                <div class="space-y-4 xl:col-span-3">

                {{-- ══════════ ENTRI OBAT — dipisah Non Racikan / Racikan seperti e-resep ══════════
                   | Tab dikendalikan dari server (bukan Alpine x-show) karena isi modal ini
                   | sering di-morph Livewire; island Alpine per-tab gampang putus di situ.
                   ══════════════════════════════════════════════════════════════════════════════ --}}
                <x-tabs variant="underline">
                    <x-tab :active="$tabObat === 'NonRacikan'" wire:click="$set('tabObat', 'NonRacikan')">
                        Non Racikan
                    </x-tab>
                    <x-tab :active="$tabObat === 'Racikan'" wire:click="$set('tabObat', 'Racikan')">
                        Racikan
                    </x-tab>
                </x-tabs>

                {{-- Tiap tab satu komponen ANAK yang berdiri sendiri — meniru e-resep RJ.
                   | Anak membaca & menulis node JSON-nya masing-masing (obat vs obatRacikan),
                   | lalu mengabarkan induk lewat `apotek-online-rj.obat-berubah`.
                   | wire:key MEMUAT rjNo supaya ganti pasien = mount ulang, bukan data basi. --}}
                <div class="mt-2">
                    @if ($tabObat === 'NonRacikan')
                        <livewire:pages::transaksi.rj.apotek-online-rj.apotek-online-rj-non-racikan
                            :rjNo="$rjNo" :isFormLocked="$statusKlaim === 'terkirim'"
                            wire:key="ao-non-racikan-{{ $rjNo }}" />
                    @else
                        <livewire:pages::transaksi.rj.apotek-online-rj.apotek-online-rj-racikan
                            :rjNo="$rjNo" :isFormLocked="$statusKlaim === 'terkirim'"
                            wire:key="ao-racikan-{{ $rjNo }}" />
                    @endif
                </div>

                <div class="text-xs text-muted dark:text-gray-400">
                    {{ $this->jumlahSiap() }} obat siap kirim
                    @if ($this->jumlahBelumDpho() > 0)
                        · <span class="text-warning-deep dark:text-amber-300">{{ $this->jumlahBelumDpho() }} belum dipetakan DPHO (dilewati)</span>
                    @endif
                </div>

                {{-- LOG HASIL --}}
                @if ($log)
                    <div class="p-3 space-y-1 text-xs border rounded-lg bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                        @foreach ($log as $barisLog)
                            <div class="{{ $barisLog['ok'] ? 'text-success' : 'text-error-deep dark:text-red-300' }}">
                                {{ $barisLog['ok'] ? '✓' : '✗' }} {{ $barisLog['teks'] }}
                            </div>
                        @endforeach
                    </div>
                @endif
                {{-- PERINGATAN SIGNA --}}
                <div class="px-3 py-2 text-xs border rounded-lg bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                    <strong>Periksa Signa &amp; Jumlah Hari (JHO)</strong> tiap obat sebelum kirim.
                    Jumlah (Jml) terisi dari <strong>porsi kronis</strong> (qty_kronis), bukan qty resep utuh.
                    Pemetaan signa ke format BPJS (berapa kali × berapa) belum baku — betulkan bila perlu.
                    Daftar obat bisa disusun ulang: hapus baris, atau tambah obat DPHO lain di bawah.
                </div>

                </div>{{-- /kanan: payload klaim --}}
                </div>{{-- /grid dua kolom --}}
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 flex items-center justify-end gap-2 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'apotek-online-rj-actions' })">
                    Tutup
                </x-secondary-button>
                @if ($statusKlaim !== 'terkirim')
                    <x-outline-button type="button" wire:click="simpanDraf" wire:loading.attr="disabled" wire:target="simpanDraf">
                        <span wire:loading.remove wire:target="simpanDraf">Simpan Draf</span>
                        <span wire:loading wire:target="simpanDraf">Menyimpan...</span>
                    </x-outline-button>
                    <x-primary-button type="button" wire:click="kirim" wire:loading.attr="disabled" wire:target="kirim"
                        :disabled="$this->jumlahSiap() === 0">
                        <span wire:loading.remove wire:target="kirim">Daftarkan &amp; Kirim</span>
                        <span wire:loading wire:target="kirim"><x-loading /> Mengirim...</span>
                    </x-primary-button>
                @else
                    <x-badge variant="success">Terkirim · SJP {{ $noSjpApotek }}</x-badge>
                @endif
            </div>
        </div>
    </x-modal>
</div>
