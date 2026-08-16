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

    #[On('apotek-online-rj.daftarkan')]
    public function open(string $rjNo): void
    {
        $this->reset(['obatList', 'log', 'noSjpApotek', 'sedangKirim']);
        $this->statusKlaim = 'draft';
        $this->rjNo = $rjNo;

        $data = $this->findDataRJ($rjNo);
        if (empty($data)) {
            $this->dispatch('toast', type: 'error', message: 'Data RJ tidak ditemukan.');
            return;
        }

        $this->muatHeader($data);

        // RESTORE dari JSON bila sudah pernah disetup/dikirim — supaya yang tampil
        // adalah yang benar-benar disusun petugas (draft) atau yang dikirim, BUKAN
        // menghitung ulang dari rjobats. Kalau belum ada, baru bibit dari rjobats.
        $tersimpan = $data['apotekOnline'] ?? [];
        if (!empty($tersimpan['obat'])) {
            $this->restoreDari($tersimpan);
        } else {
            $this->bibitDariKronis($data);
        }

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
        $this->obatList = array_values(array_filter($ao['obat'] ?? [], 'is_array'));
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
        $obat = DB::table('rstxn_rjobats as o')
            ->join('immst_products as p', 'o.product_id', '=', 'p.product_id')
            ->where('o.rj_no', $this->rjNo)
            ->where('o.status_kronis', 'Y')
            ->where('o.qty_kronis', '>', 0)
            ->select('o.product_id', 'p.product_name', 'p.kode_dpho',
                'o.qty_kronis', 'o.status_iter', 'o.iter_qty',
                'o.rj_carapakai', 'o.catatan_khusus')
            ->orderBy('o.rjobat_dtl')
            ->get();

        $baris = [];
        foreach ($obat as $o) {
            $baris[] = [
                'jenis' => 'nonRacikan',       // split kronis RJ hanya untuk non-racikan
                'noRacikan' => '',
                'productId' => (string) $o->product_id,
                'nama' => (string) ($o->product_name ?? '-'),
                'kodeDpho' => (string) ($o->kode_dpho ?? ''),
                // JMLOBT = porsi KRONIS. Signa & JHO tebakan awal — WAJIB diverifikasi
                // (e-resep/rjobats tak menyimpan signa dalam format BPJS yang baku).
                'signa1' => '1',
                'signa2' => '1',
                'jml' => (string) (int) $o->qty_kronis,
                'jho' => (string) (int) $o->qty_kronis,
                'catatan' => trim((string) ($o->catatan_khusus ?: $o->rj_carapakai)),
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
        $this->obatList = $baris;
    }

    /* ── Edit daftar obat (seperti e-resep: hapus / tambah) ─────────────── */

    public function hapusBaris(int $index): void
    {
        if ($this->statusKlaim === 'terkirim') {
            return; // yang sudah terkirim tak diubah dari sini
        }
        unset($this->obatList[$index]);
        $this->obatList = array_values($this->obatList);
    }

    /** Tambah obat dari LOV DPHO — untuk resep yang disusun ulang / obat berbeda. */
    #[On('lov.selected.apotekOnlineTambah')]
    public function tambahObat(string $target, array $payload): void
    {
        $kode = trim((string) ($payload['kode'] ?? ''));
        if ($kode === '' || $this->statusKlaim === 'terkirim') {
            return;
        }
        // Cegah dobel kode DPHO yang sama.
        foreach ($this->obatList as $o) {
            if (trim($o['kodeDpho']) === $kode) {
                $this->dispatch('toast', type: 'info', message: 'Obat itu sudah ada di daftar.');
                return;
            }
        }
        $this->obatList[] = [
            'jenis' => 'nonRacikan', 'noRacikan' => '',
            'productId' => '', 'nama' => (string) ($payload['nama'] ?? $kode),
            'kodeDpho' => $kode,
            'signa1' => '1', 'signa2' => '1', 'jml' => '', 'jho' => '', 'catatan' => '',
        ];
    }

    /** Obat yang siap kirim = punya kode DPHO. */
    private function siap(): array
    {
        return array_values(array_filter($this->obatList, fn($o) => trim($o['kodeDpho']) !== ''));
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
            foreach ($this->siap() as $o) {
                $dasar = [
                    'NOSJP' => $noSjp, 'NORESEP' => $noResep,
                    'KDOBT' => $o['kodeDpho'], 'NMOBAT' => $o['nama'],
                    'SIGNA1OBT' => (int) $o['signa1'], 'SIGNA2OBT' => (int) $o['signa2'],
                    'JMLOBT' => (int) $o['jml'], 'JHO' => (int) $o['jho'],
                    'CatKhsObt' => $o['catatan'],
                ];

                if ($o['jenis'] === 'racikan') {
                    $dasar['JNSROBT'] = $o['noRacikan'];
                    $dasar['PERMINTAAN'] = 1;
                    $resp = $this->apotek_obat_racikan_insert($dasar)->getData(true);
                } else {
                    $resp = $this->apotek_obat_nonracikan_insert($dasar)->getData(true);
                }

                if ((string) ($resp['metadata']['code'] ?? '') === '200') {
                    $terkirim++;
                    $this->log[] = ['ok' => true, 'teks' => 'Obat terkirim: ' . $o['nama']];
                } else {
                    $this->log[] = ['ok' => false, 'teks' => $o['nama'] . ' — ' . ($resp['metadata']['message'] ?? 'ditolak')];
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
                'obat' => array_values($this->obatList),
                'ubahAt' => now(env('APP_TIMEZONE'))->format('Y-m-d H:i:s'),
                'ubahOleh' => (string) (auth()->user()->myuser_name ?? auth()->user()->name ?? '-'),
            ];

            if ($status === 'terkirim') {
                $node['jmlObatTerkirim'] = $terkirim;
                $node['kirimAt'] = now(env('APP_TIMEZONE'))->format('Y-m-d H:i:s');
                $node['kirimOleh'] = (string) (auth()->user()->myuser_name ?? auth()->user()->name ?? '-');
            } else {
                // Draft ulang tidak menghapus jejak kirim sebelumnya (kalau ada).
                foreach (['jmlObatTerkirim', 'kirimAt', 'kirimOleh'] as $k) {
                    if (isset($lama[$k])) $node[$k] = $lama[$k];
                }
            }

            $data['apotekOnline'] = $node;
            $this->updateJsonRJ($this->rjNo, $data);
        });
    }
};
?>

<div>
    <x-modal name="apotek-online-rj-actions" size="4xl" focusable>
        <div class="flex flex-col max-h-[85vh]">

            {{-- HEADER --}}
            <div class="px-6 py-4 border-b border-hairline dark:border-gray-700">
                <h2 class="text-lg font-semibold text-ink dark:text-gray-100">Daftarkan &amp; Kirim ke Apotek Online</h2>
                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                    <span class="font-semibold">{{ $info['regName'] ?? '-' }}</span>
                    · RM {{ $info['regNo'] ?? '-' }}
                    · SEP {{ ($info['noSep'] ?? '') ?: '(tidak ada)' }}
                    · {{ $info['poliDesc'] ?? '-' }}
                </p>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-6 py-5 space-y-4 overflow-y-auto bg-surface-soft/60 dark:bg-gray-950/20">

                {{-- HEADER RESEP --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label value="Jenis Obat (KDJNSOBAT)" />
                        <x-select-input wire:model="kdJnsObat" class="w-full mt-1">
                            <option value="1">Obat PRB</option>
                            <option value="2">Obat Kronis Belum Stabil</option>
                            <option value="3">Obat Kemoterapi</option>
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label value="Iterasi" />
                        <x-select-input wire:model="iterasi" class="w-full mt-1">
                            <option value="0">Non-Iterasi</option>
                            <option value="1">Iterasi</option>
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label value="No. Resep (maks 5)" />
                        <x-text-input wire:model="noResep" maxlength="5" @disabled($statusKlaim === 'terkirim')
                            class="w-full mt-1" />
                    </div>
                </div>

                {{-- PERINGATAN SIGNA --}}
                <div class="px-3 py-2 text-xs border rounded-lg bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200">
                    <strong>Periksa Signa &amp; Jumlah Hari (JHO)</strong> tiap obat sebelum kirim.
                    Jumlah (Jml) terisi dari <strong>porsi kronis</strong> (qty_kronis), bukan qty resep utuh.
                    Pemetaan signa ke format BPJS (berapa kali × berapa) belum baku — betulkan bila perlu.
                    Daftar obat bisa disusun ulang: hapus baris, atau tambah obat DPHO lain di bawah.
                </div>

                {{-- TABEL OBAT --}}
                <div class="overflow-x-auto border rounded-lg border-hairline dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-card dark:bg-gray-800">
                            <tr class="text-xs font-semibold text-left uppercase text-muted dark:text-gray-300">
                                <th class="px-3 py-2">Obat</th>
                                <th class="px-3 py-2">Kode DPHO</th>
                                <th class="px-3 py-2 w-16">Signa1</th>
                                <th class="px-3 py-2 w-16">Signa2</th>
                                <th class="px-3 py-2 w-16">Jml</th>
                                <th class="px-3 py-2 w-16">JHO</th>
                                @if ($statusKlaim !== 'terkirim')
                                    <th class="px-3 py-2 w-10"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($obatList as $i => $o)
                                <tr wire:key="obat-{{ $i }}"
                                    class="border-t border-hairline dark:border-gray-700 {{ $o['kodeDpho'] === '' ? 'opacity-60' : '' }}">
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-ink dark:text-gray-100">{{ $o['nama'] }}</div>
                                        <div class="text-xs text-muted-soft">
                                            {{ $o['jenis'] === 'racikan' ? 'Racikan ' . $o['noRacikan'] : 'Non-racikan' }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        @if ($o['kodeDpho'] !== '')
                                            <span class="font-mono text-xs text-success">{{ $o['kodeDpho'] }}</span>
                                        @else
                                            <span class="text-xs text-warning-deep dark:text-amber-300">belum dipetakan</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2"><x-text-input wire:model="obatList.{{ $i }}.signa1" class="w-14 !py-1" :disabled="$statusKlaim === 'terkirim'" /></td>
                                    <td class="px-3 py-2"><x-text-input wire:model="obatList.{{ $i }}.signa2" class="w-14 !py-1" :disabled="$statusKlaim === 'terkirim'" /></td>
                                    <td class="px-3 py-2"><x-text-input wire:model="obatList.{{ $i }}.jml" class="w-14 !py-1" :disabled="$statusKlaim === 'terkirim'" /></td>
                                    <td class="px-3 py-2"><x-text-input wire:model="obatList.{{ $i }}.jho" class="w-14 !py-1" :disabled="$statusKlaim === 'terkirim'" /></td>
                                    @if ($statusKlaim !== 'terkirim')
                                        <td class="px-3 py-2">
                                            <button type="button" wire:click="hapusBaris({{ $i }})"
                                                class="text-error-deep hover:text-red-700 dark:text-red-300" title="Hapus obat">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-3 py-8 text-center text-muted">Belum ada obat. Tambah obat DPHO di bawah.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- TAMBAH OBAT (resep disusun ulang / obat berbeda) --}}
                @if ($statusKlaim !== 'terkirim')
                    <div>
                        <x-input-label value="Tambah obat DPHO" />
                        <div class="mt-1 sm:max-w-md">
                            <livewire:lov.dpho.lov-dpho target="apotekOnlineTambah"
                                wire:key="lov-tambah-{{ $rjNo }}-{{ count($obatList) }}" />
                        </div>
                    </div>
                @endif

                <div class="text-xs text-muted dark:text-gray-400">
                    {{ $this->jumlahSiap() }} obat siap kirim
                    @if ($this->jumlahBelumDpho() > 0)
                        · <span class="text-warning-deep dark:text-amber-300">{{ $this->jumlahBelumDpho() }} belum dipetakan DPHO (dilewati)</span>
                    @endif
                </div>

                {{-- LOG HASIL --}}
                @if ($log)
                    <div class="p-3 space-y-1 text-xs border rounded-lg bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
                        @foreach ($log as $l)
                            <div class="{{ $l['ok'] ? 'text-success' : 'text-error-deep dark:text-red-300' }}">
                                {{ $l['ok'] ? '✓' : '✗' }} {{ $l['teks'] }}
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- FOOTER --}}
            <div class="flex items-center justify-end gap-2 px-6 py-4 border-t bg-canvas border-hairline dark:bg-gray-900 dark:border-gray-700">
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
