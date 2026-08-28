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
use App\Support\NoSep;

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

    /** Tab entri obat, meniru e-resep: NonRacikan | Racikan | Verifikasi. */
    public string $tabObat = 'NonRacikan';

    /**
     * Hasil tarikan tab Verifikasi. Dua daftar terpisah karena datang dari dua
     * endpoint berbeda: daftar RESEP milik apotek (rentang tanggal) dan daftar
     * OBAT milik satu SJP.
     */
    public array $daftarResepBpjs = [];
    public array $daftarObatBpjs = [];
    public string $verifikasiPesan = '';
    public bool $verifikasiGagal = false;


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
            'tabObat', 'permintaanRacikan',
            'daftarResepBpjs', 'daftarObatBpjs', 'verifikasiPesan', 'verifikasiGagal']);
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
            // vno_sep kolom TEKS BEBAS (ITER/…, LAB, BATAL) — wajib diekstrak.
            'noSep' => NoSep::ekstrak($pasien->vno_sep ?? null),
            'noSepAsli' => (string) ($pasien->vno_sep ?? ''),
            'sepCatatan' => NoSep::catatan($pasien->vno_sep ?? null),
            'poliDesc' => $pasien->poli_desc ?? '-',
            'drName' => $pasien->dr_name ?? '-',
            'rjDate' => $pasien->rj_date ?? '',
            'statusKronisHdr' => $pasien->status_kronis ?? 'N',
            'statusIterHdr' => $pasien->status_iter ?? 'N',
        ];
    }

    /** Pulihkan setup yang tersimpan di JSON. */
    private function restoreDari(array $apotekOnline): void
    {
        $this->kdJnsObat = (string) ($apotekOnline['kdJnsObat'] ?? '1');
        $this->iterasi = (string) ($apotekOnline['iterasi'] ?? '0');
        // Klaim lama menampilkan nomor yang BENAR-BENAR dikirim, bukan hasil hitung
        // ulang — kalau aturannya pernah berubah, jejaknya tetap jujur.
        $this->noResep = (string) ($apotekOnline['noResep'] ?? '') ?: self::noResepDari($this->rjNo);
        $this->noSjpApotek = (string) ($apotekOnline['noSjp'] ?? '');
        $this->statusKlaim = (string) ($apotekOnline['status'] ?? ($this->noSjpApotek !== '' ? 'terkirim' : 'draft'));
        // obatList & permintaanRacikan TIDAK diisi di sini — keduanya cermin yang
        // dibaca ulang oleh segarkanDaftar() supaya cuma ada satu jalur muat.
    }

    /**
     * No. Resep apotek = 5 digit terakhir No. RJ. TIDAK BISA DIUBAH PETUGAS.
     *
     * BPJS menuntut NORESEP maksimal 5 digit dan TIDAK BOLEH SAMA dalam satu bulan
     * klaim (aturan tak tertulis di Trust Mark; checklist UAT mengujinya di butir 9.8).
     * Menurunkannya dari rj_no membuat keunikan itu terjamin dengan sendirinya —
     * rj_no primary key, dan tabrakan baru mungkin bila ada dua No. RJ berselisih
     * TEPAT 100.000 dalam bulan yang sama, yang berarti ~100 ribu kunjungan sebulan
     * (nyatanya ~2.600). Jadi tak perlu pengecekan lintas-bulan sama sekali.
     *
     * rj_no sendiri tak bisa dipakai utuh: 573.462 baris sudah 6 digit.
     *
     * Sumber lama "resepNo dari e-resep" DIBUANG — EresepJson::lembar() memang selalu
     * mengembalikan resepNo null untuk jalur RJ (penomoran lembar hanya ada di RI),
     * jadi cabang itu tak pernah sekali pun terpakai dan cuma menyesatkan pembaca.
     */
    /* ═══════════ VERIFIKASI & KOREKSI DI SISI BPJS ═══════════
     | Empat endpoint yang membaca/menghapus apa yang SUDAH tersimpan di BPJS —
     | bukan menyusun kiriman baru. Berpasangan dua-dua:
     |   daftar resep (rentang tanggal)  <-> hapus resep  (seluruh SJP)
     |   daftar obat  (satu SJP)         <-> hapus obat   (satu baris)
     | Semuanya menembak BPJS sungguhan, jadi tak ada yang berjalan otomatis. */

    private function catatVerifikasi(array $balasan, string $judul): bool
    {
        $kode = (string) ($balasan['metadata']['code'] ?? '');
        $pesan = (string) ($balasan['metadata']['message'] ?? 'tanpa keterangan');
        $this->verifikasiGagal = $kode !== '200';
        $this->verifikasiPesan = $judul . ($this->verifikasiGagal ? ' ditolak — ' : ' berhasil — ') . $pesan;

        return !$this->verifikasiGagal;
    }

    /** Modul 10 — daftar resep apotek pada rentang tanggal kunjungan ini. */
    public function tarikDaftarResep(): void
    {
        $tglKunjungan = Carbon::parse($this->info['rjDate'] ?? now());

        $balasan = $this->apotek_resep_daftar([
            'kdppk' => (string) env('APOTEK_KDPPK'),
            'KdJnsObat' => '0',
            'JnsTgl' => 'TGLRSP',
            'TglMulai' => $tglKunjungan->copy()->startOfDay()->format('Y-m-d H:i:s'),
            'TglAkhir' => $tglKunjungan->copy()->endOfDay()->format('Y-m-d H:i:s'),
        ])->getData(true);

        $this->daftarResepBpjs = $this->catatVerifikasi($balasan, 'Daftar resep')
            ? self::ratakanBalasan($balasan['response'] ?? [])
            : [];
    }

    /** Modul 14 — daftar obat yang tersimpan di BPJS untuk SJP klaim ini. */
    public function tarikDaftarObat(): void
    {
        if (blank($this->noSjpApotek)) {
            $this->verifikasiGagal = true;
            $this->verifikasiPesan = 'Belum ada No. SJP — klaim ini belum pernah terkirim.';
            return;
        }

        $balasan = $this->apotek_pelayanan_daftar($this->noSjpApotek)->getData(true);

        $this->daftarObatBpjs = $this->catatVerifikasi($balasan, 'Daftar obat')
            ? self::ratakanBalasan($balasan['response'] ?? [])
            : [];
    }

    /** Modul 11 — hapus SELURUH resep di BPJS. */
    public function hapusResepBpjs(): void
    {
        if (blank($this->noSjpApotek)) {
            $this->verifikasiGagal = true;
            $this->verifikasiPesan = 'Belum ada No. SJP yang bisa dihapus.';
            return;
        }

        // Ejaan field di sini HURUF KECIL semua, berbeda dari saat menyimpan
        // (NORESEP/REFASALSJP). Menyalin nama field dari simpan akan ditolak.
        $balasan = $this->apotek_resep_hapus([
            'nosjp' => $this->noSjpApotek,
            'refasalsjp' => (string) ($this->info['noSep'] ?? ''),
            'noresep' => $this->noResep,
        ])->getData(true);

        if ($this->catatVerifikasi($balasan, 'Hapus resep')) {
            // Klaim tak lagi ada di BPJS: kembalikan layar ke keadaan draf supaya
            // petugas bisa menyusun & mengirim ulang.
            $this->noSjpApotek = '';
            $this->statusKlaim = 'draft';
            $this->daftarObatBpjs = [];
            $data = $this->findDataRJ($this->rjNo);
            $this->simpanNode($data, 'draft', '', 0);
            $this->dispatch('apotek-online-rj.refresh');
        }
    }

    /** Modul 13 — hapus SATU baris obat di BPJS. */
    public function hapusObatBpjs(string $kodeObat, string $tipeObat): void
    {
        if (blank($this->noSjpApotek)) {
            $this->verifikasiGagal = true;
            $this->verifikasiPesan = 'Belum ada No. SJP yang bisa dikoreksi.';
            return;
        }

        $balasan = $this->apotek_pelayanan_hapus([
            'nosepapotek' => $this->noSjpApotek,
            'noresep' => $this->noResep,
            'kodeobat' => $kodeObat,
            'tipeobat' => $tipeObat !== '' ? $tipeObat : 'N',
        ])->getData(true);

        if ($this->catatVerifikasi($balasan, 'Hapus obat ' . $kodeObat)) {
            $this->tarikDaftarObat();
        }
    }

    /** Ratakan balasan jadi daftar baris — bentuknya tak seragam antar endpoint. */
    private static function ratakanBalasan(mixed $response): array
    {
        if (!is_array($response) || $response === []) {
            return [];
        }

        if (array_is_list($response)) {
            return array_values(array_filter($response, 'is_array'));
        }

        foreach ($response as $isi) {
            if (is_array($isi) && array_is_list($isi) && $isi !== [] && is_array($isi[0])) {
                return $isi;
            }
        }

        return [$response];
    }

    /** Batas hari resep terhadap tanggal SEP menurut BPJS (checklist UAT butir 9.9). */
    private const BATAS_HARI_RESEP = 15;

    /**
     * Selisih hari tanggal resep terhadap tanggal SEP, atau null bila tanggal SEP
     * tak terbaca — dalam hal itu pengiriman TIDAK dihalangi, biar BPJS yang menilai;
     * menolak karena format tanggal mereka berubah akan lebih merugikan.
     *
     * Nilai negatif berarti resep MENDAHULUI SEP; itu bukan pelanggaran batas 15 hari,
     * jadi dibiarkan lewat dan cukup ditolak BPJS bila memang salah.
     */
    private static function selisihHariResepDariSep(string $tglSep, Carbon $tglResep): ?int
    {
        $tglSep = trim($tglSep);
        if ($tglSep === '') {
            return null;
        }

        try {
            $awal = Carbon::parse($tglSep)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        // Selisih dihitung dari timestamp mentah, BUKAN diffInDays(..., false):
        // di Carbon 3 tanda hasil diff terbalik dari Carbon 2 dan sudah pernah membuat
        // laporan lain salah hitung senyap. Lihat memory carbon3-diff-signed.
        $selisihDetik = $tglResep->copy()->startOfDay()->getTimestamp() - $awal->getTimestamp();

        return intdiv($selisihDetik, 86400);
    }

    private static function noResepDari(?string $rjNo): string
    {
        return substr(preg_replace('/\D/', '', (string) $rjNo), -5);
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

        // Signa dari e-resep dokter. Field-nya memang sepasang dengan milik BPJS:
        // di form e-resep, signaX berlabel "Signa1" dan signaHari berlabel "Signa2".
        // Dipetakan per productId supaya baris kronis mengambil signa obatnya sendiri.
        $signaEresep = [];
        foreach (EresepJson::lembar($data) as $lembarEresep) {
            foreach ($lembarEresep['nonRacikan'] as $obatEresep) {
                $productId = trim((string) ($obatEresep['productId'] ?? ''));
                if ($productId === '') {
                    continue;
                }
                $signaEresep[$productId] = [
                    'signa1' => (int) ($obatEresep['signaX'] ?? 0),
                    'signa2' => (int) ($obatEresep['signaHari'] ?? 0),
                ];
            }
        }

        $obatBibit = [];
        foreach ($obatKronisList as $obatKronis) {
            $productId = (string) $obatKronis->product_id;
            $signa1 = $signaEresep[$productId]['signa1'] ?? 0;
            $signa2 = $signaEresep[$productId]['signa2'] ?? 0;

            // Obat yang tak ketemu di e-resep (mis. ditambahkan langsung di Administrasi)
            // tetap dapat nilai 1 supaya JHO tidak dibagi nol.
            $signa1 = $signa1 > 0 ? $signa1 : 1;
            $signa2 = $signa2 > 0 ? $signa2 : 1;

            // JHO = JUMLAH HARI obat, bukan jumlah butir. Sebelumnya diisi qty_kronis
            // sehingga 30 tablet terkirim sebagai "30 hari" walau diminum 3x sehari.
            $qtyKronis = (int) $obatKronis->qty_kronis;
            $jho = (int) max(1, ceil($qtyKronis / ($signa1 * $signa2)));

            $obatBibit[] = [
                'jenis' => 'nonRacikan',       // split kronis RJ hanya untuk non-racikan
                'noRacikan' => '',
                'productId' => $productId,
                'nama' => (string) ($obatKronis->product_name ?? '-'),
                'kodeDpho' => (string) ($obatKronis->kode_dpho ?? ''),
                // JMLOBT = porsi KRONIS. Signa & JHO tebakan awal — WAJIB diverifikasi
                // (e-resep/rjobats tak menyimpan signa dalam format BPJS yang baku).
                'signa1' => (string) $signa1,
                'signa2' => (string) $signa2,
                'jml' => (string) $qtyKronis,
                'jho' => (string) $jho,
                'catatan' => trim((string) ($obatKronis->catatan_khusus ?: $obatKronis->rj_carapakai)),
            ];
        }

        $this->noResep = self::noResepDari($this->rjNo);

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

        $apotekOnline = $this->findDataRJ($this->rjNo)['apotekOnline'] ?? [];

        $this->obatList = array_merge(
            array_values(array_filter($apotekOnline['obat'] ?? [], 'is_array')),
            array_values(array_filter($apotekOnline['obatRacikan'] ?? [], 'is_array')),
        );
        $this->permintaanRacikan = array_filter(
            (array) ($apotekOnline['permintaanRacikan'] ?? []),
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
            $tglKunjungan = Carbon::parse($this->info['rjDate'] ?? now());

            // 1) POLIRSP dari SEP (BPJS pakai kode poli-nya sendiri, bukan poli_id kita).
            // Tanpa nomor SEP tak ada yang bisa dikirim — REFASALSJP wajib dan BPJS
            // menuntut tepat 19 karakter. Dihentikan di sini, bukan di ujung rantai.
            if (blank($this->info['noSep'] ?? '')) {
                $this->gagal('Kolom SEP kunjungan ini tidak memuat nomor SEP ('
                    . (($this->info['noSepAsli'] ?? '') ?: 'kosong') . '). Betulkan di Pendaftaran lebih dulu.');
                return;
            }

            $sep = $this->apotek_sep($this->info['noSep'])->getData(true);
            if ((string) ($sep['metadata']['code'] ?? '') !== '200') {
                $this->gagal('Baca SEP gagal — ' . ($sep['metadata']['message'] ?? 'gangguan BPJS'));
                return;
            }
            $poliRsp = (string) ($sep['response']['poli'] ?? '');

            // SEP asal & kode dokter diambil dari BALASAN BPJS, bukan dari kolom kita:
            // itu bentuk yang mereka akui sendiri. Kalau kosong, baru pakai hasil ekstraksi.
            $sepAsal = (string) ($sep['response']['noSep'] ?? '') ?: $this->info['noSep'];
            $kdDokter = (string) ($sep['response']['kodedokter'] ?? '');

            $this->log[] = ['ok' => true, 'teks' => 'SEP terbaca. Poli resep: ' . ($poliRsp ?: '(kosong)')];

            // BATAS 15 HARI. BPJS menolak resep yang tanggalnya lebih dari 15 hari dari
            // tanggal SEP (checklist UAT butir 9.9). Dijaga di sini supaya petugas tahu
            // sebelum menempuh sisa rantai, bukan setelah resep terlanjur dibuat di sana.
            // Tanggal SEP diambil dari respons BPJS (tglsep), bukan dari tanggal kunjungan
            // kita — keduanya bisa berbeda bila SEP dibuat mundur.
            $selisihHari = self::selisihHariResepDariSep(
                (string) ($sep['response']['tglsep'] ?? ''),
                $tglKunjungan
            );

            if ($selisihHari !== null && $selisihHari > self::BATAS_HARI_RESEP) {
                $this->gagal(
                    'Resep melewati batas ' . self::BATAS_HARI_RESEP . ' hari dari tanggal SEP — '
                    . 'SEP ' . ($sep['response']['tglsep'] ?? '?') . ', resep '
                    . $tglKunjungan->format('Y-m-d') . ' (selisih ' . $selisihHari . ' hari).'
                );
                return;
            }

            // 2) Simpan resep → dapat No. SJP apotek.
            $noResep = (string) ($this->noResep ?: '1');
            $resep = $this->apotek_resep_insert([
                'TGLSJP' => $tglKunjungan->format('Y-m-d H:i:s'),
                'REFASALSJP' => $sepAsal,
                'POLIRSP' => $poliRsp,
                'KDJNSOBAT' => $this->kdJnsObat,
                'NORESEP' => $noResep,
                'IDUSERSJP' => (string) (auth()->user()->myuser_code ?? 'SIMRS'),
                'TGLRSP' => $tglKunjungan->format('Y-m-d 00:00:00'),
                'TGLPELRSP' => now()->format('Y-m-d 00:00:00'),
                // Dipetakan dari respons SEP; katalog membolehkan kosong, jadi '' bila
                // BPJS memang tak mengirimnya — bukan dipaku '0' seperti sebelumnya.
                'KdDokter' => $kdDokter,
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
                                <x-input-label value="No. Resep" />
                                {{-- Read-only: diturunkan dari No. RJ supaya syarat BPJS "tak boleh sama
                                   | dalam satu bulan klaim" terpenuhi dengan sendirinya. Lihat noResepDari(). --}}
                                <x-text-input wire:model="noResep" readonly tabindex="-1"
                                    title="Otomatis dari 5 digit terakhir No. RJ — dijamin unik, tidak bisa diubah"
                                    class="w-full mt-1 cursor-not-allowed opacity-70" />
                                <p class="mt-1 text-xs text-muted-soft">otomatis dari No. RJ</p>
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

                    {{-- CARA PAKAI — gaya biru-info standar repo, DEFAULT TERTUTUP.
                       | Memakai <details> native, bukan toggle Alpine: isi modal ini sering
                       | di-morph Livewire dan island Alpine gampang putus di situ. --}}
                    <details class="mt-4 overflow-hidden border border-blue-200 bg-blue-50 rounded-2xl dark:bg-blue-900/20 dark:border-blue-700 group">
                        <summary class="flex items-center gap-3 px-4 py-3 cursor-pointer select-none">
                            <svg class="w-4 h-4 text-blue-700 transition-transform dark:text-blue-300 shrink-0 group-open:rotate-90"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                            <div class="flex flex-wrap items-baseline gap-x-2">
                                <span class="text-sm font-semibold text-ink dark:text-gray-100">Cara Pakai</span>
                                <span class="text-xs text-muted dark:text-gray-400">— urutan mendaftarkan klaim obat ke BPJS</span>
                            </div>
                        </summary>

                        <div class="px-4 pt-1 pb-4 space-y-3 border-t border-blue-200 dark:border-blue-800">
                            <ol class="pl-4 mt-3 space-y-2 text-xs list-decimal text-body dark:text-gray-300">
                                <li>
                                    Periksa <strong>Jenis Obat</strong> dan <strong>Iterasi</strong> di atas.
                                    No. Resep terisi otomatis dari No. RJ dan tidak bisa diubah — itu yang menjamin
                                    nomornya tak kembar dalam satu bulan klaim.
                                </li>
                                <li>
                                    Susun obat di tab <strong>Non Racikan</strong> dan <strong>Racikan</strong>.
                                    Daftar awal dibibitkan dari obat kronis yang sudah ditandai di Administrasi;
                                    tambah obat DPHO lain lewat kotak pencarian di atas tabel.
                                </li>
                                <li>
                                    Cocokkan dengan panel <strong>E-Resep dari Dokter</strong> di atas. Obat yang
                                    ikut diklaim bertanda centang hijau — wajar bila jumlahnya lebih sedikit,
                                    karena yang diklaim hanya porsi kronisnya.
                                </li>
                                <li>
                                    Betulkan <strong>Signa1</strong>, <strong>Signa2</strong>, dan
                                    <strong>JHO</strong> bila perlu, lalu tekan
                                    <strong>Daftarkan &amp; Kirim</strong>. Rantainya: baca SEP → simpan resep →
                                    kirim tiap obat. Berhenti di kegagalan pertama, dan tiap langkah tercatat di log.
                                </li>
                                <li>
                                    Sesudah terkirim, tab <strong>Verifikasi &amp; Koreksi</strong> dipakai
                                    memeriksa apa yang benar-benar tersimpan di BPJS — dan menghapusnya bila salah.
                                </li>
                            </ol>

                            <div class="pt-3 space-y-1.5 text-xs border-t border-blue-200 dark:border-blue-800 text-muted dark:text-gray-400">
                                <p><strong class="text-body dark:text-gray-300">Obat tanpa kode DPHO dilewati</strong> saat kirim — petakan dulu di Master Obat.</p>
                                <p><strong class="text-body dark:text-gray-300">Resep maksimal 15 hari</strong> dari tanggal SEP; lebih dari itu ditahan sebelum dikirim.</p>
                                <p><strong class="text-body dark:text-gray-300">Simpan Draf</strong> menyimpan susunan tanpa mengirim; entri obat sendiri tersimpan otomatis tiap diubah.</p>
                            </div>
                        </div>
                    </details>
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
                    <x-tab :active="$tabObat === 'Verifikasi'" wire:click="$set('tabObat', 'Verifikasi')">
                        Verifikasi &amp; Koreksi
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
                    @elseif ($tabObat === 'Racikan')
                        <livewire:pages::transaksi.rj.apotek-online-rj.apotek-online-rj-racikan
                            :rjNo="$rjNo" :isFormLocked="$statusKlaim === 'terkirim'"
                            wire:key="ao-racikan-{{ $rjNo }}" />
                    @else
                        {{-- VERIFIKASI & KOREKSI — membaca/menghapus apa yang SUDAH tersimpan
                           | di BPJS. Tidak ada yang berjalan otomatis: tiap tombol menembak
                           | BPJS sungguhan dan terhitung kuota. --}}
                        <div class="space-y-4">

                            @if ($verifikasiPesan)
                                <div class="px-3 py-2 text-sm border rounded-lg
                                    {{ $verifikasiGagal
                                        ? 'bg-error/10 border-error/30 text-error-deep dark:text-red-300'
                                        : 'bg-success/10 border-success/30 text-success' }}">
                                    {{ $verifikasiPesan }}
                                </div>
                            @endif

                            {{-- MODUL 14 + 13: daftar obat satu SJP, dan koreksi per baris --}}
                            <div class="border rounded-lg border-hairline dark:border-gray-700">
                                <div class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-hairline dark:border-gray-700 bg-surface-card dark:bg-gray-800">
                                    <span class="text-sm font-semibold text-ink dark:text-gray-100">Obat tersimpan di BPJS</span>
                                    <span class="text-xs text-muted dark:text-gray-400">
                                        {{ $noSjpApotek ? 'SJP ' . $noSjpApotek : 'belum ada No. SJP' }}
                                    </span>
                                    <x-outline-button type="button" class="ml-auto shrink-0" wire:click="tarikDaftarObat"
                                        wire:loading.attr="disabled" wire:target="tarikDaftarObat">
                                        <span wire:loading.remove wire:target="tarikDaftarObat">Tarik Data Obat</span>
                                        <span wire:loading wire:target="tarikDaftarObat"><x-loading /> Menarik...</span>
                                    </x-outline-button>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-surface-card dark:bg-gray-800">
                                            <tr class="text-xs font-semibold text-left uppercase text-muted dark:text-gray-300">
                                                @forelse (array_keys($daftarObatBpjs[0] ?? []) as $kolom)
                                                    <th class="px-3 py-2 whitespace-nowrap">{{ $kolom }}</th>
                                                @empty
                                                    <th class="px-3 py-2">Obat</th>
                                                @endforelse
                                                <th class="w-24 px-3 py-2"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($daftarObatBpjs as $indeks => $barisObat)
                                                <tr wire:key="obat-bpjs-{{ $indeks }}" class="border-t border-hairline dark:border-gray-700">
                                                    @foreach (array_keys($daftarObatBpjs[0]) as $kolom)
                                                        <td class="px-3 py-2 align-top text-body dark:text-gray-300">
                                                            {{ is_scalar($barisObat[$kolom] ?? null) ? $barisObat[$kolom] : json_encode($barisObat[$kolom] ?? null, JSON_UNESCAPED_UNICODE) }}
                                                        </td>
                                                    @endforeach
                                                    <td class="px-3 py-2">
                                                        {{-- Kode & tipe obat dibaca dari balasan BPJS sendiri; nama
                                                           | field-nya berbeda antar versi, jadi dicoba beberapa. --}}
                                                        @php
                                                            $kodeObat = (string) ($barisObat['kodeobat'] ?? $barisObat['kdobat'] ?? $barisObat['KDOBT'] ?? '');
                                                            $tipeObat = (string) ($barisObat['tipeobat'] ?? $barisObat['jnsobat'] ?? 'N');
                                                        @endphp
                                                        @if ($kodeObat !== '')
                                                            <x-outline-button type="button"
                                                                wire:click="hapusObatBpjs('{{ $kodeObat }}', '{{ $tipeObat }}')"
                                                                wire:confirm="Hapus obat {{ $kodeObat }} dari klaim di BPJS?"
                                                                wire:loading.attr="disabled"
                                                                class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300
                                                                       dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30
                                                                       dark:hover:!bg-red-900/30 dark:hover:!text-red-300 !px-2 !py-1 text-xs">
                                                                Hapus
                                                            </x-outline-button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="2" class="px-3 py-8 text-center text-muted">
                                                        Belum ditarik. Tekan <strong>Tarik Data Obat</strong> untuk melihat apa yang tersimpan di BPJS.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- MODUL 10: daftar resep apotek pada tanggal kunjungan ini --}}
                            <div class="border rounded-lg border-hairline dark:border-gray-700">
                                <div class="flex flex-wrap items-center gap-3 px-3 py-2 border-b border-hairline dark:border-gray-700 bg-surface-card dark:bg-gray-800">
                                    <span class="text-sm font-semibold text-ink dark:text-gray-100">Resep apotek di BPJS</span>
                                    <span class="text-xs text-muted dark:text-gray-400">tanggal kunjungan ini</span>
                                    <x-outline-button type="button" class="ml-auto shrink-0" wire:click="tarikDaftarResep"
                                        wire:loading.attr="disabled" wire:target="tarikDaftarResep">
                                        <span wire:loading.remove wire:target="tarikDaftarResep">Tarik Daftar Resep</span>
                                        <span wire:loading wire:target="tarikDaftarResep"><x-loading /> Menarik...</span>
                                    </x-outline-button>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-surface-card dark:bg-gray-800">
                                            <tr class="text-xs font-semibold text-left uppercase text-muted dark:text-gray-300">
                                                @forelse (array_keys($daftarResepBpjs[0] ?? []) as $kolom)
                                                    <th class="px-3 py-2 whitespace-nowrap">{{ $kolom }}</th>
                                                @empty
                                                    <th class="px-3 py-2">Resep</th>
                                                @endforelse
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($daftarResepBpjs as $indeks => $barisResep)
                                                <tr wire:key="resep-bpjs-{{ $indeks }}" class="border-t border-hairline dark:border-gray-700">
                                                    @foreach (array_keys($daftarResepBpjs[0]) as $kolom)
                                                        <td class="px-3 py-2 align-top text-body dark:text-gray-300">
                                                            {{ is_scalar($barisResep[$kolom] ?? null) ? $barisResep[$kolom] : json_encode($barisResep[$kolom] ?? null, JSON_UNESCAPED_UNICODE) }}
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="1" class="px-3 py-8 text-center text-muted">
                                                        Belum ditarik. Tekan <strong>Tarik Daftar Resep</strong>.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- MODUL 11: hapus seluruh SJP resep --}}
                            @if (filled($noSjpApotek))
                                <div class="flex flex-wrap items-center gap-3 px-3 py-3 border rounded-lg border-error/30 bg-error/5">
                                    <p class="flex-1 min-w-[16rem] text-xs text-error-deep dark:text-red-300">
                                        Menghapus SELURUH resep SJP {{ $noSjpApotek }} di BPJS beserta obat di dalamnya.
                                        Klaim akan kembali berstatus draf dan bisa disusun ulang.
                                    </p>
                                    <x-outline-button type="button"
                                        wire:click="hapusResepBpjs"
                                        wire:confirm="Hapus seluruh resep SJP {{ $noSjpApotek }} di BPJS?"
                                        wire:loading.attr="disabled" wire:target="hapusResepBpjs"
                                        class="shrink-0 !text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700 hover:!border-red-300
                                               dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30
                                               dark:hover:!bg-red-900/30 dark:hover:!text-red-300">
                                        <span wire:loading.remove wire:target="hapusResepBpjs">Hapus Resep di BPJS</span>
                                        <span wire:loading wire:target="hapusResepBpjs"><x-loading /> Menghapus...</span>
                                    </x-outline-button>
                                </div>
                            @endif

                        </div>
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
                    Signa1 &amp; Signa2 diambil dari e-resep dokter; JHO dihitung Jml ÷ (Signa1 × Signa2).
                    Obat yang tidak ada di e-resep — mis. ditambahkan langsung di Administrasi — jatuh ke 1 × 1.
                    Daftar obat bisa disusun ulang: hapus baris, atau tambah obat DPHO lain di atas.
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
