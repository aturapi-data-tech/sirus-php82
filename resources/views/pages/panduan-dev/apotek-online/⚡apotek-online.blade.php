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
            ['SEP alfanumerik, bukan digit', 'Nomor SEP 19 KARAKTER alfanumerik (mis. "0184R0060726V001670" ada huruf R/V). Validasi digits:19 menolak setiap SEP asli — pakai size:19.'],
            ['Klaim porsi KRONIS, bukan qty resep', 'JMLOBT = rstxn_rjobats.qty_kronis (porsi di luar paket INA-CBG), BUKAN qty resep utuh. qty_bpjs sudah ditagihkan lewat INA-CBG; mengklaimnya lagi = klaim ganda. Kirim hanya obat status_kronis=Y.'],
            ['Signa tak baku', 'e-resep/rjobats tak simpan signa dalam format BPJS (SIGNA1×SIGNA2). Modal membuatnya EDITABLE + tebakan awal — petugas verifikasi. Jangan tebak diam-diam: dosis salah = klaim salah.'],
        ];
    }

    /** Modul SIMRS yang membungkus trait — 3 layar + node JSON. */
    public function modul(): array
    {
        return [
            ['Master Obat — LOV kode DPHO', '/master/obat (bagian 8)', 'Memetakan tiap obat ke KODE DPHO (KDOBT) lewat LOV bersumber referensi/dpho. Kolom immst_products.kode_dpho. DIPILIH bukan diketik — salah satu digit = obat berbeda. Hidup sebelum CID aktif: tampilkan "belum bisa diakses" sampai koneksi hidup.'],
            ['Worklist Apotek Online RJ', '/apotek-online/rj', 'Daftar resep BPJS ber-SEP, mode Harian/Bulanan ala Casemix. Kolom obat menghitung obat KRONIS (rstxn_rjobats status_kronis=Y) + berapa yang siap (ber-DPHO). Tombol Daftarkan membuka modal.'],
            ['Modal Daftarkan & Kirim', 'dari worklist', 'Merakit payload dari SEP + obat kronis + kode_dpho, jalankan rantai sep→resep_insert→obat_*_insert. Obat BISA disusun ulang (hapus/tambah DPHO/edit signa) seperti e-resep. Simpan Draf terpisah dari Kirim; setelah terkirim terkunci.'],
            ['Laporan Klaim', '/manajemen/rs/apotek-online/laporan-klaim', 'Monitoring rekap per periode (apotek_monitoring_klaim) ala Evaluasi Rujukan Keluar: tarik manual, kartu rekap, tabel listsep, unduh CSV.'],
        ];
    }

    /** Struktur node apotekOnline di datadaftarpolirj_json. */
    public function nodeJson(): array
    {
        return [
            ['status', '"draft" | "terkirim"'],
            ['noSjp', 'No. SJP apotek dari BPJS (kosong saat draft)'],
            ['kdJnsObat / iterasi / noResep', 'header resep — bisa diubah petugas'],
            ['obat[]', 'DAFTAR OBAT penuh: {jenis, noRacikan, productId, nama, kodeDpho, signa1, signa2, jml, jho, catatan}. Nama key = bentuk modal (bukan KDOBT/SIGNA1OBT) supaya restore langsung nyambung; konversi ke nama BPJS saat kirim.'],
            ['ubahAt / ubahOleh', 'jejak sunting terakhir'],
            ['kirimAt / kirimOleh / jmlObatTerkirim', 'hanya ada bila status "terkirim"'],
        ];
    }

    /** Peta perjalanan data — dari master lokal sampai terkirim ke BPJS. Dipakai di bab Pendahuluan. */
    public function peta(): array
    {
        return [
            ['Master', 'Master Obat → kode DPHO', 'Tiap obat lokal dipetakan ke KDOBT DPHO. Lokal, tak butuh BPJS aktif.'],
            ['Worklist', 'Apotek Online RJ', 'Resep ber-SEP dipilih; sistem menghitung porsi KRONIS yang layak klaim.'],
            ['Modal', 'Daftarkan & Kirim', 'Payload dirakit dari SEP + obat kronis + kode DPHO; petugas boleh menyunting.'],
            ['BPJS', 'Rantai sep→resep→obat', 'Lima panggilan berurutan ke apotek-rest. Butuh CID aktif.'],
            ['Jejak', 'Node JSON + Laporan', 'Hasil disimpan penuh di apotekOnline; rekap klaim ditarik di Laporan.'],
        ];
    }

    /**
     * Endpoint yang SUDAH tersambung ke layar SIMRS — SATU-SATUNYA sumber kebenaran
     * daftar "dipakai". Menambah pemakaian endpoint baru? Tambahkan barisnya di sini,
     * maka otomatis hilang dari daftar "belum dipakai". [method => layar tempat dipanggil]
     */
    public function dipakai(): array
    {
        return [
            'apotek_referensi_dpho'         => 'Master Obat — LOV kode DPHO',
            'apotek_sep'                    => 'Modal — kirim() langkah 1',
            'apotek_resep_insert'           => 'Modal — kirim() langkah 2',
            'apotek_obat_nonracikan_insert' => 'Modal — kirim() langkah 3',
            'apotek_obat_racikan_insert'    => 'Modal — kirim() langkah 3',
            'apotek_monitoring_klaim'       => 'Laporan Klaim',
        ];
    }

    /**
     * Endpoint yang BELUM tersambung ke layar mana pun — dihitung dari method trait
     * dikurangi daftar dipakai(), lalu diberi alasan singkat "kenapa belum". Kalau ada
     * method trait tanpa alasan di sini, halaman mengaku (label "— (belum dijelaskan)").
     *
     * @return array<int, array{0:string, 1:string}>  [method, alasan]
     */
    public function belumDipakai(): array
    {
        $alasan = [
            'apotek_pelayanan_daftar'        => 'Verifikasi hasil kirim (bandingkan yang tersimpan di BPJS vs yang dikirim). Rantai kirim() saat ini berhenti setelah insert obat lalu simpan JSON — pemanggilan verifikasi masih berupa TODO/komentar, tinggal disambung.',
            'apotek_pelayanan_hapus'         => 'Hapus SATU baris obat pasca kirim. Belum ada UI koreksi per-baris; pembatalan sekarang hanya lewat susun-ulang draft sebelum kirim.',
            'apotek_pelayanan_riwayat'        => 'Cek obat kronis sama sudah pernah ditebus lintas SJP (anti-tebus-ganda antar bulan). Fitur pencegahan itu belum dibangun.',
            'apotek_resep_hapus'             => 'Hapus SELURUH SJP resep (batalkan klaim). Belum ada tombol "Batalkan Klaim" di modal.',
            'apotek_resep_daftar'            => 'Daftar resep terkirim per periode. Laporan sekarang memakai monitoring/klaim yang sudah beragregat, jadi endpoint ini belum diperlukan.',
            'apotek_update_stok'             => 'Sinkron stok ke BPJS — hanya relevan bila flag checkstock pada Setting Apotek bernilai "True". Belum diaktifkan.',
            'apotek_prb_rekap_peserta'       => 'Rekap peserta PRB per bulan. Laporan PRB terpisah belum dibuat (dan barisnya ganda — perlu penyaringan di pemanggil).',
            'apotek_referensi_setting'       => 'Identitas apoteker/kepala/verifikator + flag checkstock. Belum ditampilkan di layar mana pun.',
            'apotek_referensi_poli'          => 'Lookup kode poli BPJS. Poli sudah datang dari response apotek_sep (POLIRSP), jadi lookup manual belum perlu.',
            'apotek_referensi_faskes'        => 'Cari kode faskes. Faskes = milik sendiri (APOTEK_KDPPK), tak perlu dicari.',
            'apotek_referensi_obat'          => 'Harga obat berlaku per tanggal. DPHO penuh sudah cukup untuk LOV kode_dpho; harga-per-tanggal baru perlu saat verifikasi biaya.',
            'apotek_referensi_spesialistik'  => 'Daftar spesialistik. Belum ada pemakainya.',
        ];

        $dipakai = array_keys($this->dipakai());
        $out = [];
        foreach ((new ReflectionClass(\App\Http\Traits\BPJS\ApotekTrait::class))->getMethods() as $m) {
            $nama = $m->getName();
            if (! str_starts_with($nama, 'apotek_') || in_array($nama, $dipakai, true)) {
                continue;
            }
            $out[] = [$nama, $alasan[$nama] ?? '— (belum dijelaskan di halaman ini)'];
        }

        // Urutkan mengikuti urutan alasan di atas supaya pengelompokan logis, sisanya di belakang.
        $urutan = array_flip(array_keys($alasan));
        usort($out, fn($a, $b) => ($urutan[$a[0]] ?? 999) <=> ($urutan[$b[0]] ?? 999));

        return $out;
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
        $modul = $this->modul();
        $peta = $this->peta();
        $dipakai = $this->dipakai();
        $belumDipakai = $this->belumDipakai();
        $sinkron = $this->cekSinkron();
        $totalEndpoint = $sinkron['trait'];
        $jmlDipakai = count($dipakai);
        $jmlBelum = count($belumDipakai);
        $melenceng = $sinkron['hilang'] || $sinkron['berlebih'];

        // Sidebar bab — dikelompokkan mengikuti perjalanan data: Persiapan → Alur Klaim → Referensi.
        $menuGroups = [
            'Persiapan' => [
                'pendahuluan' => 'Pendahuluan & Peta',
                'master-dpho' => 'Master Obat → kode DPHO',
            ],
            'Alur Klaim' => [
                'worklist' => 'Worklist Apotek RJ',
                'modal'    => 'Modal Daftarkan & Kirim',
                'rantai'   => 'Rantai Kirim ke BPJS',
                'laporan'  => 'Laporan & Monitoring',
            ],
            'Referensi' => [
                'katalog'      => 'Katalog Endpoint',
                'belum-dipakai' => 'API Belum Dipakai',
                'jebakan'      => 'Jebakan Payload',
                'setup'        => 'Env & Kredensial',
            ],
        ];
        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('panduan-dev') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Apotek Online</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('panduan-dev') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">← Standarisasi UI</a>
                    <x-theme-toggle />
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Sumber kebenaran: <span class="ds-code">app/Http/Traits/BPJS/ApotekTrait.php</span><br>
                            Skill: <span class="ds-code">/apotek-online</span><br>
                            Ruang lingkup aktif: <span class="ds-code">transaksi/rj</span> (RJ)
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    {{-- ====== 01 PENDAHULUAN & PETA ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Persiapan</div>
                        <h1 class="ds-display-md mb-4">Dari Master Obat sampai terkirim ke BPJS</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Web service BPJS <code class="ds-code">apotek-rest</code> untuk klaim
                            <strong>obat PRB, kronis belum stabil, dan kemoterapi</strong> — BUKAN pelayanan
                            apotek harian (itu <code class="ds-code">/transaksi/apotek</code>). Tutorial ini
                            mengikuti <strong>satu perjalanan data</strong>: dari pemetaan obat di Master,
                            lewat worklist &amp; modal, sampai lima panggilan ke BPJS dan jejaknya di JSON.
                            Seluruh <strong>{{ $totalEndpoint }} endpoint</strong> katalog Trust Mark sudah
                            terbungkus di <code class="ds-code">App\Http\Traits\BPJS\ApotekTrait</code>.
                        </p>

                        <h2 class="ds-title-lg mt-8 mb-4">Peta perjalanan · {{ count($peta) }} tahap</h2>
                        <div style="display:flex;flex-direction:column;gap:10px">
                            @foreach ($peta as $i => [$tahap, $layar, $ket])
                                <div class="ds-card-outline" style="display:flex;gap:16px;align-items:flex-start">
                                    <span class="ds-display-lg" style="color:var(--primary);line-height:1;min-width:32px">{{ $i + 1 }}</span>
                                    <div style="flex:1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="ds-title-sm" style="color:var(--ink)">{{ $tahap }}</span>
                                            <code class="ds-code" style="color:var(--primary)">{{ $layar }}</code>
                                        </div>
                                        <p class="ds-body-sm mt-1" style="color:var(--body)">{{ $ket }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Status: dua penghalang di luar kode</h2>
                        <div class="ds-card-outline" style="padding:14px 18px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Per 16/08/2026 belum ada endpoint yang teruji fungsional.</strong>
                                (1) CID <code class="ds-code">6323</code> masih dibalas
                                <code class="ds-code">Unauthorized! Consumer ID is expired!</code> — menunggu
                                diaktifkan BPJS. (2) <code class="ds-code">kode_dpho</code> belum dipetakan.
                                Yang SUDAH terbukti benar: URL, nama service, dan signature — permintaan sampai
                                ke layanan Apotek, bukan tersasar ke gateway. Yang murni lokal (Master Obat,
                                Worklist) sudah bisa dipakai sekarang.
                            </span>
                        </div>

                        {{-- Halaman ini membandingkan katalognya sendiri dengan method sungguhan di
                             ApotekTrait, lalu mengaku bila sudah ketinggalan. --}}
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
                    </section>

                    {{-- ====== 02 MASTER OBAT → KODE DPHO ====== --}}
                    <section x-show="section === 'master-dpho'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Persiapan</div>
                        <h1 class="ds-display-md mb-4">Master Obat → kode DPHO</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Perjalanan dimulai jauh sebelum ada pasien: <strong>tiap obat lokal harus punya
                            jodoh di katalog DPHO BPJS</strong>. Tanpa jembatan ini, obat yang diklaim tak
                            bisa dikenali BPJS. Inilah kerja yang bisa (dan harus) diselesaikan lebih dulu.
                        </p>

                        <div class="ds-card-outline mb-6" style="display:flex;flex-direction:column;gap:8px">
                            <div class="ds-title-sm" style="color:var(--ink)">{{ $modul[0][0] }}</div>
                            <code class="ds-code" style="color:var(--primary)">{{ $modul[0][1] }}</code>
                            <p class="ds-body-sm" style="color:var(--body)">{{ $modul[0][2] }}</p>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Kenapa di <span class="ds-code">immst_products</span>, bukan tabel kronis</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Kolom <code class="ds-code">immst_products.kode_dpho</code> (VARCHAR2(20) + index).
                            Ditaruh di master OBAT, bukan <code class="ds-code">rsmst_listobatbpjses</code>:
                            DPHO adalah sifat <strong>obat</strong>, bukan kekronisannya — dan Apotek Online juga
                            melayani PRB &amp; kemo, bukan cuma kronis. DDL:
                            <code class="ds-code">database/sql/2026_08_16_alter_immst_products_add_kode_dpho.sql</code>.
                        </p>

                        <div class="ds-card-outline" style="padding:14px 18px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Kode DPHO <strong>DIPILIH lewat LOV, bukan diketik</strong> — salah satu digit =
                                obat berbeda, dan itu TIDAK ditolak saat kirim. Sumber LOV:
                                <code class="ds-code">apotek_referensi_dpho()</code> (cache 1 jam). LOV hidup
                                sebelum CID aktif: menampilkan "belum bisa diakses" sampai koneksi hidup, dan
                                kegagalan TIDAK di-cache.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 03 WORKLIST ====== --}}
                    <section x-show="section === 'worklist'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Alur Klaim</div>
                        <h1 class="ds-display-md mb-4">Worklist Apotek Online RJ</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Layar tempat petugas memilih resep yang akan diklaim. Meniru Casemix: mode
                            <strong>Harian/Bulanan</strong>, filter tanggal <code class="ds-code">dd/mm/yyyy</code>
                            &amp; <code class="ds-code">mm/yyyy</code>, paginasi bernomor.
                        </p>

                        <div class="ds-card-outline mb-6" style="display:flex;flex-direction:column;gap:8px">
                            <div class="ds-title-sm" style="color:var(--ink)">{{ $modul[1][0] }}</div>
                            <code class="ds-code" style="color:var(--primary)">{{ $modul[1][1] }}</code>
                            <p class="ds-body-sm" style="color:var(--body)">{{ $modul[1][2] }}</p>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-3">Sumber obat = porsi KRONIS, bukan e-resep penuh</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Ini keputusan paling menentukan seluruh modul. Kolom obat pada worklist menghitung
                            baris <code class="ds-code">rstxn_rjobats</code> WHERE
                            <code class="ds-code">status_kronis='Y'</code> — bukan seluruh e-resep.
                        </p>
                        <div class="ds-card-outline" style="padding:14px 18px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <code class="ds-code">JMLOBT = qty_kronis</code> (porsi di luar paket INA-CBG),
                                <strong>BUKAN qty resep utuh</strong>. <code class="ds-code">qty_bpjs</code> sudah
                                ditagihkan lewat INA-CBG; mengklaimnya lagi = <strong>klaim ganda</strong>.
                                Worklist juga menandai berapa obat yang sudah ber-DPHO (siap kirim).
                            </span>
                        </div>
                    </section>

                    {{-- ====== 04 MODAL ====== --}}
                    <section x-show="section === 'modal'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Alur Klaim</div>
                        <h1 class="ds-display-md mb-4">Modal Daftarkan &amp; Kirim</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Jantung modul. Merakit payload dari SEP + obat kronis + kode DPHO, lalu menjalankan
                            rantai kirim. Obat <strong>bisa disusun ulang</strong> (hapus/tambah DPHO/edit signa)
                            persis seperti e-resep — petugas bisa mengganti obat atau men-setup ulang untuk pasien itu.
                        </p>

                        <div class="ds-card-outline mb-6" style="display:flex;flex-direction:column;gap:8px">
                            <div class="ds-title-sm" style="color:var(--ink)">{{ $modul[2][0] }}</div>
                            <code class="ds-code" style="color:var(--primary)">{{ $modul[2][1] }}</code>
                            <p class="ds-body-sm" style="color:var(--body)">{{ $modul[2][2] }}</p>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-4">Jejak klaim: <code class="ds-code">datadaftarpolirj_json.apotekOnline</code></h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Saat kirim (dan saat Simpan Draf), setup &amp; hasil disimpan penuh di satu node JSON —
                            <strong>daftar obat lengkap, bukan cuma nomor SJP</strong>. Draft bertahan saat modal
                            ditutup; klaim terkirim bisa ditinjau ulang persis seperti yang dikirim.
                        </p>
                        <div class="ds-card-outline" style="padding:0;overflow-x:auto">
                            <table class="w-full text-sm" style="border-collapse:collapse">
                                <tbody>
                                    @foreach ($this->nodeJson() as [$key, $ket])
                                        <tr style="border-top:1px solid var(--hairline)">
                                            <td class="px-4 py-2" style="white-space:nowrap;vertical-align:top">
                                                <code class="ds-code" style="color:var(--primary)">{{ $key }}</code>
                                            </td>
                                            <td class="px-4 py-2 ds-body-sm" style="color:var(--body)">{{ $ket }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-body-sm mt-4" style="color:var(--muted-soft)">
                            Nama key obat memakai bentuk MODAL (bukan <code class="ds-code">KDOBT</code>/<code class="ds-code">SIGNA1OBT</code>)
                            supaya restore ke modal langsung nyambung; konversi ke nama BPJS dilakukan saat <code class="ds-code">kirim()</code>.
                        </p>
                    </section>

                    {{-- ====== 05 RANTAI KIRIM ====== --}}
                    <section x-show="section === 'rantai'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Alur Klaim</div>
                        <h1 class="ds-display-md mb-4">Rantai kirim ke BPJS · {{ count($alur) }} langkah</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Tombol Kirim di modal menjalankan lima panggilan <code class="ds-code">apotek-rest</code>
                            berurutan. Nomor SJP apotek dari langkah 2 menjadi kunci semua langkah sesudahnya.
                        </p>

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

                    {{-- ====== 06 LAPORAN ====== --}}
                    <section x-show="section === 'laporan'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Alur Klaim</div>
                        <h1 class="ds-display-md mb-4">Laporan &amp; Monitoring</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Ujung perjalanan: memantau status klaim yang sudah dikirim. Meniru Evaluasi Rujukan
                            Keluar — tarik manual per periode, kartu rekap, tabel, unduh CSV.
                        </p>

                        <div class="ds-card-outline mb-6" style="display:flex;flex-direction:column;gap:8px">
                            <div class="ds-title-sm" style="color:var(--ink)">{{ $modul[3][0] }}</div>
                            <code class="ds-code" style="color:var(--primary)">{{ $modul[3][1] }}</code>
                            <p class="ds-body-sm" style="color:var(--body)">{{ $modul[3][2] }}</p>
                        </div>

                        <div class="ds-card-outline" style="padding:14px 18px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Sumbernya <code class="ds-code">apotek_monitoring_klaim($bulan, $tahun, $jenis, $status)</code>
                                — urutan <strong>BULAN dulu</strong>. Pada status 1 (belum diverifikasi),
                                <code class="ds-code">totalbiayasetuju = 0</code> itu wajar, BUKAN penolakan.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 07 KATALOG ENDPOINT ====== --}}
                    <section x-show="section === 'katalog'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Katalog Endpoint · {{ $totalEndpoint }} method</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Seluruh endpoint <code class="ds-code">apotek-rest</code> yang terbungkus di trait,
                            dikelompokkan per menu Trust Mark. Method berawalan <code class="ds-code">apotek_</code>
                            (wajib — satu komponen bisa memakai ApotekTrait + VclaimTrait sekaligus). Badge
                            <strong style="color:var(--primary)">dipakai</strong> menandai yang sudah tersambung ke
                            layar SIMRS ({{ $jmlDipakai }}/{{ $totalEndpoint }}); sisanya lihat bab
                            <button type="button" x-on:click="go('belum-dipakai')" class="ds-code hover:underline" style="color:var(--primary);cursor:pointer;background:none;border:none;padding:0">API Belum Dipakai</button>.
                        </p>

                        @foreach ($katalog as [$menu, $items])
                            <h2 class="ds-title-lg mt-8 mb-4">{{ $menu }} <span class="ds-body-sm" style="color:var(--muted-soft)">· {{ count($items) }} endpoint</span></h2>
                            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                                @foreach ($items as [$method, $http, $path, $catatan])
                                    @php $terpakai = array_key_exists($method, $dipakai); @endphp
                                    <div class="ds-card-outline" style="display:flex;flex-direction:column;gap:8px">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="ds-spike"></span>
                                            <code class="ds-code" style="font-size:15px;font-weight:600;color:var(--primary)">{{ $method }}()</code>
                                            @if ($terpakai)
                                                <span class="ds-caption-up" style="color:var(--primary);border:1px solid var(--primary);border-radius:5px;padding:1px 7px">dipakai</span>
                                            @else
                                                <span class="ds-caption-up" style="color:var(--muted-soft);border:1px solid var(--hairline);border-radius:5px;padding:1px 7px">belum</span>
                                            @endif
                                        </div>
                                        <div class="ds-body-sm" style="color:var(--muted)">
                                            <strong>{{ $http }}</strong> <code class="ds-code">{{ $path }}</code>
                                        </div>
                                        <p class="ds-body-sm" style="color:var(--body)">{{ $catatan }}</p>
                                        @if ($terpakai)
                                            <p class="ds-caption" style="color:var(--muted-soft)">↳ {{ $dipakai[$method] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </section>

                    {{-- ====== 08 API BELUM DIPAKAI ====== --}}
                    <section x-show="section === 'belum-dipakai'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Referensi</div>
                        <h1 class="ds-display-md mb-4">API Belum Dipakai · {{ $jmlBelum }} dari {{ $totalEndpoint }}</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Baru <strong>{{ $jmlDipakai }} endpoint</strong> yang tersambung ke layar
                            (<code class="ds-code">apotek_referensi_dpho</code>, <code class="ds-code">apotek_sep</code>,
                            <code class="ds-code">apotek_resep_insert</code>, <code class="ds-code">apotek_obat_*_insert</code>,
                            <code class="ds-code">apotek_monitoring_klaim</code>). Sisanya sudah SIAP di trait tapi
                            belum ada pemakainya — daftar berikut dihitung otomatis dari method trait dikurangi
                            yang dipakai, jadi tak bisa basi diam-diam.
                        </p>

                        <div style="display:flex;flex-direction:column;gap:12px">
                            @foreach ($belumDipakai as [$method, $alasan])
                                <div class="ds-card-outline" style="display:flex;flex-direction:column;gap:6px">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="ds-spike"></span>
                                        <code class="ds-code" style="font-size:15px;font-weight:600;color:var(--ink)">{{ $method }}()</code>
                                        <span class="ds-caption-up" style="color:var(--muted-soft);border:1px solid var(--hairline);border-radius:5px;padding:1px 7px">belum</span>
                                    </div>
                                    <p class="ds-body-sm" style="color:var(--body)">{{ $alasan }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:14px 18px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Menyambung salah satunya? Tambahkan barisnya ke <code class="ds-code">dipakai()</code>
                                di komponen ini — ia otomatis pindah ke "dipakai" di Katalog dan hilang dari daftar ini.
                                Yang paling dekat: <code class="ds-code">apotek_pelayanan_daftar</code> (verifikasi
                                pasca-kirim, tinggal disambung di <code class="ds-code">kirim()</code>).
                            </span>
                        </div>
                    </section>

                    {{-- ====== 08 JEBAKAN ====== --}}
                    <section x-show="section === 'jebakan'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Jebakan Payload · {{ count($jebakan) }} hal</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
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

                    {{-- ====== 09 SETUP ====== --}}
                    <section x-show="section === 'setup'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Env &amp; Kredensial</h1>

                        <div class="ds-card-dark" style="padding:22px">
<pre class="ds-code" style="margin:0;color:var(--on-dark-soft);overflow-x:auto;font-size:13px"><span style="color:var(--muted-soft)"># .env — blok APOTEK_* BERDIRI SENDIRI dari VCLAIM_*</span>
APOTEK_URL = "https://apijkn-dev.bpjs-kesehatan.go.id/apotek-rest-dev/"   <span style="color:var(--muted-soft)"># development</span>
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

                    {{-- ============ PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6" style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">
                            ← <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])">
                            <span x-text="labels[order[idx() + 1]]"></span> →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
