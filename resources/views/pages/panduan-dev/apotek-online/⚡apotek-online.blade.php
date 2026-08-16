<?php

use Livewire\Component;

// Dokumentasi web modul APOTEK ONLINE BPJS (apotek-rest).
// Sumber kebenaran: app/Http/Traits/BPJS/ApotekTrait.php + Trust Mark → Katalog WS → Apotek.
// Halaman referensi: katalog endpoint, alur pemakaian, dan jebakan yang sudah dipetakan.
new class extends Component {
    /** Alur baku sekali penebusan resep, urut. */
    public function alur(): array
    {
        return [
            ['1', 'Baca SEP asal', 'apotek_sep($noSep)', 'Bukan sekadar verifikasi peserta — response.poli mengisi POLIRSP, response.flagprb menentukan KDJNSOBAT, response.noSep jadi REFASALSJP. Tanpa langkah ini dua field resep hanya bisa ditebak.'],
            ['2', 'Simpan resep', 'apotek_resep_insert($r)', 'Menghasilkan No. SJP apotek (noApotik) — dipakai SEMUA langkah sesudahnya sebagai NOSJP. Simpan nomornya.'],
            ['3', 'Simpan obat', 'apotek_obat_nonracikan_insert() / apotek_obat_racikan_insert()', 'Satu panggilan per baris obat. Untuk racikan, semua bahan dikirim terpisah dengan JNSROBT yang SAMA.'],
            ['4', 'Periksa hasil', 'apotek_pelayanan_daftar($noSjpApotek)', 'Memastikan yang tersimpan di BPJS sama dengan yang kita kirim. Parameternya SJP apotek, bukan SEP asal.'],
            ['5', 'Pantau klaim', 'apotek_monitoring_klaim($bulan, $tahun, $jenisObat, $status)', 'Rekap siap pakai: totalbiayapengajuan vs totalbiayasetuju. Pada status 1 (belum diverifikasi) biayasetuju 0 itu wajar, bukan penolakan.'],
        ];
    }

    /** Katalog endpoint per menu Trust Mark. [menu, [ [method trait, http, path, catatan], ... ] ] */
    public function katalog(): array
    {
        return [
            ['Referensi', [
                ['apotek_referensi_dpho', 'GET', 'referensi/dpho', 'Katalog penuh obat DPHO. Penanda prb/kronis/kemo berupa STRING "True"/"False", bukan boolean.'],
                ['apotek_referensi_poli', 'GET', 'referensi/poli/{kode|nama}', 'Menerima kode maupun nama. Kode poli BPJS BUKAN poli_id lokal.'],
                ['apotek_referensi_faskes', 'GET', 'referensi/ppk/{1|2}/{nama}', '1 = FKTP, 2 = RS.'],
                ['apotek_referensi_setting', 'GET', 'referensi/settingppk/read/{kode}', 'Identitas apoteker/kepala/verifikator + flag checkstock. Default memakai APOTEK_KDPPK.'],
                ['apotek_referensi_spesialistik', 'GET', 'referensi/spesialistik', 'Daftar spesialistik.'],
                ['apotek_referensi_obat', 'GET', 'referensi/obat/{jenis}/{tglResep}/{filter}', 'BEDA dari DPHO: menyaring per jenis obat DAN tanggal, jadi harganya harga yang berlaku pada tanggal itu.'],
            ]],
            ['Obat', [
                ['apotek_obat_nonracikan_insert', 'POST', 'obatnonracikan/v3/insert', 'NOSJP = SJP apotek, bukan SEP asal.'],
                ['apotek_obat_racikan_insert', 'POST', 'obatracikan/v3/insert', 'Tambahan JNSROBT (perekat racikan) & PERMINTAAN (jumlah racikan diminta, beda dari JMLOBT).'],
                ['apotek_update_stok', 'POST', 'UpdateStokObat/updatestok', 'Relevan hanya bila checkstock pada Setting Apotek "True".'],
            ]],
            ['Pelayanan Obat', [
                ['apotek_pelayanan_daftar', 'GET', 'obat/daftar/{noSjpApotek}', 'Katalog menulis "Nomor Kunjungan/SEP", tapi yang diminta SJP apotek.'],
                ['apotek_pelayanan_riwayat', 'GET', 'riwayatobat/{tglAwal}/{tglAkhir}/{noKartu}', 'Riwayat per peserta lintas SJP — memeriksa obat kronis yang sama sudah pernah ditebus.'],
                ['apotek_pelayanan_hapus', 'DELETE', 'pelayanan/obat/hapus/', 'Menghapus SATU BARIS obat. Kriteria dikirim di body.'],
            ]],
            ['Resep', [
                ['apotek_resep_insert', 'POST', 'sjpresep/v3/insert', 'KDJNSOBAT 1 PRB / 2 Kronis / 3 Kemoterapi; iterasi 0/1.'],
                ['apotek_resep_hapus', 'DELETE', 'hapusresep', 'Menghapus SELURUH SJP resep, berbeda dari hapus pelayanan obat.'],
                ['apotek_resep_daftar', 'POST', 'daftarresep', 'POST meski sifatnya membaca. JnsTgl memilih TGLPELSJP atau TGLRSP.'],
            ]],
            ['SEP · Monitoring · PRB', [
                ['apotek_sep', 'GET', 'sep/{noSep}', 'Nomor 19 digit. Titik awal seluruh alur.'],
                ['apotek_monitoring_klaim', 'GET', 'monitoring/klaim/{bulan}/{tahun}/{jenis}/{status}', 'Urutan BULAN dulu.'],
                ['apotek_prb_rekap_peserta', 'GET', 'Prb/rekappeserta/tahun/{tahun}/bulan/{bulan}', 'Urutan TAHUN dulu — kebalikan monitoring.'],
            ]],
        ];
    }

    /**
     * Bandingkan katalog di halaman ini dengan METHOD SUNGGUHAN di ApotekTrait.
     *
     * Dokumentasi yang ditulis tangan pasti melenceng cepat atau lambat: seseorang
     * menambah endpoint di trait dan lupa halaman ini, lalu halaman ini diam-diam
     * berbohong — lebih berbahaya daripada tidak ada dokumentasi. Jadi halaman ini
     * memeriksa dirinya sendiri dan mengaku bila sudah ketinggalan.
     *
     * @return array{trait:int, halaman:int, hilang:array, berlebih:array}
     */
    public function cekSinkron(): array
    {
        $didokumentasikan = collect($this->katalog())
            ->flatMap(fn($k) => collect($k[1])->pluck(0))
            ->all();

        $adaDiTrait = [];
        foreach ((new ReflectionClass(\App\Http\Traits\BPJS\ApotekTrait::class))->getMethods() as $m) {
            if (str_starts_with($m->getName(), 'apotek_')) {
                $adaDiTrait[] = $m->getName();
            }
        }

        return [
            'trait' => count($adaDiTrait),
            'halaman' => count($didokumentasikan),
            'hilang' => array_values(array_diff($adaDiTrait, $didokumentasikan)),
            'berlebih' => array_values(array_diff($didokumentasikan, $adaDiTrait)),
        ];
    }

    /** Jebakan yang sudah menggigit atau berpotensi, semuanya berbalas menyesatkan. */
    public function jebakan(): array
    {
        return [
            ['KDOBAT vs KDOBT', 'Update Stok memakai KDOBAT, insert obat memakai KDOBT — beda satu huruf. Salah eja kemungkinan dibalas 200 tapi stok tak pernah berubah, tanpa pesan apa pun.'],
            ['Huruf besar/kecil field resep', 'Hapus Resep memakai huruf KECIL semua (nosjp, refasalsjp, noresep); Simpan Resep memakai huruf BESAR untuk field yang sama. Menyalin nama antar keduanya ditolak.'],
            ['Ejaan campur di Daftar Resep', 'Dalam satu payload: kdppk huruf kecil, sisanya kapital campur (KdJnsObat, JnsTgl, TglMulai, TglAkhir). Jangan diseragamkan.'],
            ['Path berhuruf besar', 'UpdateStokObat/updatestok mengandung huruf besar, tak seperti endpoint apotek lain yang huruf kecil semua.'],
            ['Urutan parameter terbalik', 'monitoring/klaim → bulan dulu; Prb/rekappeserta → tahun dulu. Di sisi BPJS tertukar TIDAK error, hanya hasilnya kosong — gampang dikira memang tak ada data.'],
            ['JNSROBT perekat racikan', 'Semua bahan satu racikan dikirim terpisah dengan JNSROBT yang SAMA. Beda sedikit → terhitung sebagai racikan terpisah di sisi BPJS.'],
            ['JnsTgl menggeser periode klaim', 'TGLPELSJP (kapan dilayani) vs TGLRSP (kapan ditulis). Resep akhir bulan yang dilayani bulan berikutnya jatuh ke periode berbeda — keputusan akuntansi, bukan detail teknis.'],
            ['tipeobat tidak divalidasi', 'Katalog hanya memberi contoh "N" (non-racikan); nilai untuk racikan tak tertulis. Menebak "R" berisiko: baris tak ketemu dan BPJS membalas seolah tak ada yang perlu dihapus.'],
            ['Rekap PRB berbaris ganda', 'Pada contoh resmi, 16 baris ternyata hanya 8 peserta-tanggal unik — setiap baris muncul DUA KALI. Tampilkan apa adanya dan rekapnya jadi dua kali lipat. Saring di pemanggil (NomorKaPst + TglSRB + Obat).'],
            ['Format tanggal tidak seragam', 'TglSRB pada rekap PRB berformat d/m/Y H:i:s; endpoint lain Y-m-d atau Y-m-d H:i:s. Satu parser untuk semua akan gagal.'],
            ['Obat PRB satu string berkoma', 'Field Obat berisi banyak obat dipisah koma, tapi nama obatnya sendiri mengandung koma ("… inj 100 UI/ml, flexpen 3 ml"). Memecah dengan koma merusak data.'],
            ['cons-id terpisah dari VClaim', 'Kredensial VClaim yang jalan di modul lain ditolak di sini dengan "Unauthorized! You are not registered for this service!". Blok env-nya sendiri: APOTEK_*.'],
        ];
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

    @php
        $alur = $this->alur();
        $katalog = $this->katalog();
        $jebakan = $this->jebakan();
        $sinkron = $this->cekSinkron();
        $totalEndpoint = $sinkron['trait'];
        $melenceng = $sinkron['hilang'] || $sinkron['berlebih'];
    @endphp

    <div class="ds">
        <div class="ds-section">

            {{-- ============ HERO ============ --}}
            <header class="ds-band">
                <div class="flex items-center justify-between gap-2 mb-5">
                    <div class="flex items-center gap-2">
                        <span class="ds-spike"></span>
                        <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                        <span class="ds-body-sm" style="color:var(--muted-soft)">/ Standarisasi UI / Apotek Online</span>
                    </div>
                    <x-theme-toggle />
                </div>

                <a href="{{ route('panduan-dev') }}" wire:navigate class="ds-btn ds-btn-secondary mb-6"
                    style="display:inline-flex;align-items:center;gap:6px">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Kembali ke Standarisasi UI
                </a>

                <div class="ds-eyebrow mb-4">Integrasi BPJS · Apotek Online</div>
                <h1 class="ds-display-xl">Apotek Online.</h1>
                <p class="ds-body-md mt-6" style="max-width:64ch; color:var(--body-strong)">
                    Web service BPJS <code class="ds-code">apotek-rest</code> untuk klaim
                    <strong>obat PRB, kronis belum stabil, dan kemoterapi</strong>. Bukan pelayanan apotek harian —
                    yang itu sudah ditangani modul <code class="ds-code">/transaksi/apotek</code>. Seluruh
                    <strong>{{ $totalEndpoint }} endpoint</strong> katalog Trust Mark sudah terbungkus di
                    <code class="ds-code">App\Http\Traits\BPJS\ApotekTrait</code>.
                </p>

                <div class="ds-card-outline mt-6" style="padding:14px 18px">
                    <span class="ds-spike" style="vertical-align:middle"></span>
                    <span class="ds-body-sm" style="color:var(--body-strong)">
                        <strong>Status per 16/08/2026: belum ada satu pun endpoint yang teruji fungsional.</strong>
                        CID <code class="ds-code">6323</code> masih dibalas
                        <code class="ds-code">Unauthorized! Consumer ID is expired!</code> — menunggu diaktifkan BPJS
                        untuk lingkungan development. Yang SUDAH terbukti: URL, nama service, dan signature benar —
                        permintaan sampai ke layanan Apotek, bukan tersasar ke gateway.
                    </span>
                </div>

                {{-- Halaman ini membandingkan katalognya sendiri dengan method sungguhan di
                     ApotekTrait, lalu mengaku bila sudah ketinggalan. Dokumentasi yang tak
                     bisa mendeteksi dirinya basi lebih berbahaya daripada tak ada. --}}
                @if ($melenceng)
                    <div class="ds-card-outline mt-4"
                        style="padding:14px 18px;border-color:var(--warning-deep, #b45309)">
                        <span class="ds-spike" style="vertical-align:middle"></span>
                        <span class="ds-body-sm" style="color:var(--body-strong)">
                            <strong>Halaman ini sudah ketinggalan dari kodenya.</strong>
                            ApotekTrait punya {{ $sinkron['trait'] }} method, halaman ini mendaftar
                            {{ $sinkron['halaman'] }}.
                            @if ($sinkron['hilang'])
                                Belum didokumentasikan:
                                <code class="ds-code">{{ implode(', ', $sinkron['hilang']) }}</code>.
                            @endif
                            @if ($sinkron['berlebih'])
                                Didaftar di sini tapi TIDAK ADA di trait:
                                <code class="ds-code">{{ implode(', ', $sinkron['berlebih']) }}</code>.
                            @endif
                        </span>
                    </div>
                @endif
            </header>

            {{-- ============ ALUR ============ --}}
            <section class="ds-band">
                <div class="ds-eyebrow mb-3">Alur · {{ count($alur) }} langkah</div>
                <h2 class="ds-display-lg mb-8">Sekali penebusan resep</h2>

                <div style="display:flex;flex-direction:column;gap:14px">
                    @foreach ($alur as [$no, $judul, $method, $catatan])
                        <div class="ds-card-outline" style="display:flex;gap:16px;align-items:flex-start">
                            <span class="ds-display-lg" style="color:var(--primary);line-height:1;min-width:28px">{{ $no }}</span>
                            <div style="flex:1">
                                <div class="ds-title-sm" style="color:var(--ink)">{{ $judul }}</div>
                                <code class="ds-code" style="display:inline-block;margin:6px 0;color:var(--primary)">{{ $method }}</code>
                                <p class="ds-body-sm" style="color:var(--body)">{{ $catatan }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ============ KATALOG ENDPOINT ============ --}}
            @foreach ($katalog as [$menu, $items])
                <section class="ds-band">
                    <div class="ds-eyebrow mb-3">Katalog · {{ count($items) }} endpoint</div>
                    <h2 class="ds-display-lg mb-8">{{ $menu }}</h2>

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        @foreach ($items as [$method, $http, $path, $catatan])
                            <div class="ds-card-outline" style="display:flex;flex-direction:column;gap:8px">
                                <div class="flex items-center gap-2">
                                    <span class="ds-spike"></span>
                                    <code class="ds-code" style="font-size:15px;font-weight:600;color:var(--primary)">{{ $method }}()</code>
                                </div>
                                <div class="ds-body-sm" style="color:var(--muted)">
                                    <strong>{{ $http }}</strong> <code class="ds-code">{{ $path }}</code>
                                </div>
                                <p class="ds-body-sm" style="color:var(--body)">{{ $catatan }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            {{-- ============ JEBAKAN ============ --}}
            <section class="ds-band">
                <div class="ds-eyebrow mb-3">Jebakan · {{ count($jebakan) }} hal</div>
                <h2 class="ds-display-lg mb-8">Yang berbalas menyesatkan</h2>
                <p class="ds-body-md mb-8" style="max-width:60ch;color:var(--body-strong)">
                    Semua di bawah ini punya sifat sama: <strong>salah tidak menghasilkan pesan error yang
                    menunjuk sebabnya.</strong> Ada yang dibalas 200 padahal tak terjadi apa-apa, ada yang
                    mengembalikan data kosong seolah memang tak ada datanya.
                </p>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    @foreach ($jebakan as [$judul, $isi])
                        <div class="ds-card-outline" style="display:flex;flex-direction:column;gap:8px">
                            <div class="flex items-center gap-2">
                                <span class="ds-spike"></span>
                                <span class="ds-title-sm" style="color:var(--ink)">{{ $judul }}</span>
                            </div>
                            <p class="ds-body-sm" style="color:var(--body)">{{ $isi }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ============ SETUP ============ --}}
            <section class="ds-band">
                <div class="ds-eyebrow mb-3">Setup</div>
                <h2 class="ds-display-lg mb-8">Env &amp; kredensial</h2>

                <div class="ds-card-dark" style="padding:22px">
<pre class="ds-code" style="margin:0;color:var(--on-dark-soft);overflow-x:auto;font-size:13px"><span style="color:var(--muted-soft)"># .env — blok APOTEK_* BERDIRI SENDIRI dari VCLAIM_*</span>
APOTEK_URL = "https://apijkn-dev.bpjs-kesehatan.go.id/apotek-rest-dev/"   <span style="color:var(--muted-soft"># development</span>
APOTEK_CONS_ID = "…"
APOTEK_SECRET_KEY = "…"
APOTEK_USER_KEY = "…"
APOTEK_KDPPK = "0184A012"        <span style="color:var(--muted-soft)"># IFRS MADINAH TULUNGAGUNG</span>

<span style="color:var(--muted-soft)"># Naik produksi: cukup tukar komentar URL-nya</span>
<span style="color:var(--muted-soft)"># APOTEK_URL = "https://apijkn.bpjs-kesehatan.go.id/apotek-rest/"</span></pre>
                </div>

                <p class="ds-body-sm mt-4" style="color:var(--muted-soft)">
                    Contoh tanpa nilai rahasia ada di <code class="ds-code">.env.example</code>.
                    Sesudah mengubah env: <code class="ds-code">php artisan config:clear</code>, dan
                    <code class="ds-code">php artisan serve</code> yang sedang jalan harus di-restart —
                    ia tidak membaca ulang <code class="ds-code">.env</code>.
                </p>

                <div class="ds-card-outline mt-6" style="padding:14px 18px">
                    <span class="ds-spike" style="vertical-align:middle"></span>
                    <span class="ds-body-sm" style="color:var(--body-strong)">
                        Cara membedakan <strong>salah nama service</strong> dari <strong>salah kredensial</strong>:
                        nama service karangan dibalas halaman <strong>HTML gateway 404</strong>; nama service benar
                        dibalas <strong>JSON milik BPJS</strong> (<code class="ds-code">metaData.message</code>).
                        Kalau sudah dapat JSON, masalahnya di kredensial atau aktivasi — bukan di URL.
                    </span>
                </div>
            </section>

        </div>
    </div>
</div>
