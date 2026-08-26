                    {{-- ====== PETA MODUL & ALIRAN BIAYA ====== --}}
                    <section x-show="section === 'peta-modul'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Alur Visual</div>
                        <h1 class="ds-display-md mb-4">Peta Modul &amp; Aliran Biaya</h1>
                        <p class="ds-body-md mb-2" style="max-width:64ch">
                            Bab sebelumnya menceritakan perjalanan pasien. Bab ini menjawab pertanyaan
                            berikutnya: <strong>uangnya lewat mana</strong> — dari layanan yang dikerjakan,
                            sampai muncul di tagihan, kwitansi, dan pembukuan.
                        </p>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            Nama tabel &amp; aturan teknisnya ada di <strong>Sisi 2 — Coding</strong>,
                            bab <em>Peta Modul &amp; Aliran Biaya</em> dan <em>Menambah Pos Biaya Baru</em>.
                        </p>

                        @php
                            // Didefinisikan ulang di sini — bab ini tidak boleh bergantung pada
                            // urutan render bab "Alur Visual" yang mendefinisikannya lebih dulu.
                            $flowBox = function ($tone) {
                                return match ($tone) {
                                    'entry' => 'padding:10px 14px; border-color:var(--primary)',
                                    'opt'   => 'padding:10px 14px; border-style:dashed; border-color:#d97706',
                                    'cash'  => 'padding:10px 14px; border-color:#059669',
                                    'done'  => 'padding:10px 14px; border-color:#059669; background:rgba(5,150,105,0.06)',
                                    default => 'padding:10px 14px',
                                };
                            };
                            $arrow = '<span class="ds-code" style="color:var(--primary); font-size:16px">▶</span>';
                        @endphp

                        {{-- ===== 1. GAMBAR BESAR ===== --}}
                        <h2 class="ds-title-lg mb-2">1. Tiga jalur, empat modul layanan</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            <strong>Jalur kunjungan</strong> (RJ / UGD / RI) adalah yang punya kasir dan tagihan.
                            <strong>Modul layanan</strong> (Laborat, Radiologi, Kamar Operasi, Apotek) tidak punya
                            kasir sendiri — hasil kerjanya <em>menempel</em> jadi baris biaya di jalur asal pasien.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:18px 20px; overflow-x:auto">
<pre class="ds-code" style="margin:0; font-size:12.5px; line-height:1.65; color:var(--body-strong)">        MODUL LAYANAN                         JALUR KUNJUNGAN            HILIR
        (tak punya kasir)                     (punya kasir)

   ┌──────────────┐                          ┌──────────────┐
   │  Laborat     │─┐                     ┌─▶│  RJ  (poli)  │─┐
   ├──────────────┤ │                     │  ├──────────────┤ │      ┌─────────────┐
   │  Radiologi   │─┤   biaya menempel    │  │  UGD (IGD)   │─┼─────▶│  Kasir      │
   ├──────────────┤ ├────────────────────▶┤  ├──────────────┤ │      │  Kwitansi   │
   │ Kamar Operasi│─┤   ke jalur asal     │  │  RI  (inap)  │─┘      │  Jurnal     │
   ├──────────────┤ │                     │  └──────┬───────┘        │  Laporan    │
   │  Apotek      │─┘                     └─────────┘                └─────────────┘
   └──────────────┘                                 │
                                    transfer antar unit (RJ▶UGD▶RI)
                                    biaya ikut pindah, tidak ditinggal</pre>
                        </div>

                        {{-- ===== 2. TIGA POLA ===== --}}
                        <h2 class="ds-title-lg mb-2">2. Tiga pola penempelan biaya — beda, jangan disamakan</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Keempat modul layanan itu <strong>tidak bekerja dengan cara yang sama</strong>.
                            Perbedaannya menentukan apakah dibutuhkan pengaman agar pasien tak dipulangkan
                            sebelum biayanya masuk.
                        </p>

                        @php
                            $polaLayanan = [
                                [
                                    'Punya antrean sendiri + tombol transfer',
                                    'Laborat · Kamar Operasi',
                                    'entry',
                                    [
                                        ['Diorder dari EMR', 'dokter/ruangan mengirim'],
                                        ['Masuk antrean modul', 'petugas mengerjakan'],
                                        ['Ditekan tombol transfer', 'Lab: Selesai · OK: Trf Biaya'],
                                        ['Baru jadi baris tagihan', ''],
                                    ],
                                    'Ada JEDA antara diorder dan masuk tagihan. Karena itu wajib ada pengaman: kunjungan tidak boleh ditutup selagi masih ada order menggantung.',
                                ],
                                [
                                    'Langsung jadi biaya',
                                    'Radiologi',
                                    'main',
                                    [
                                        ['Diorder dari EMR', 'dokter mengirim'],
                                        ['Seketika jadi baris tagihan', 'tanpa jeda'],
                                        ['Petugas meng-upload hasil', 'ke baris yang sama'],
                                    ],
                                    'Tidak ada jeda, jadi tidak butuh pengaman. Petugas radiologi hanya melengkapi hasil, bukan membuat biayanya.',
                                ],
                                [
                                    'Lewat resep',
                                    'Apotek',
                                    'opt',
                                    [
                                        ['Dokter menulis e-resep', ''],
                                        ['Obat diserahkan', 'di apotek'],
                                        ['Jadi baris tagihan obat', ''],
                                    ],
                                    'Rawat inap punya jalur tambahan (penjualan/SLS) dengan aturan pembatalan sendiri.',
                                ],
                            ];
                        @endphp

                        @foreach ($polaLayanan as [$judulPola, $modulnya, $tone, $langkah, $catatan])
                            <div class="ds-card-outline mb-4" style="padding:14px 16px">
                                <div class="flex flex-wrap items-baseline gap-x-2 mb-3">
                                    <span class="ds-title-sm" style="color:var(--primary)">{{ $judulPola }}</span>
                                    <span class="ds-caption" style="color:var(--muted)">— {{ $modulnya }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mb-3">
                                    @foreach ($langkah as $i => [$judul, $ket])
                                        @if ($i > 0) {!! $arrow !!} @endif
                                        <span class="ds-card-outline" style="{{ $flowBox($i === count($langkah) - 1 ? 'cash' : $tone) }}; background:var(--canvas)">
                                            <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $judul }}</span>
                                            @if ($ket !== '')<span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>@endif
                                        </span>
                                    @endforeach
                                </div>
                                <p class="ds-body-sm" style="color:var(--body)">{{ $catatan }}</p>
                            </div>
                        @endforeach

                        {{-- ===== 3. SESUDAH JADI BIAYA ===== --}}
                        <h2 class="ds-title-lg mb-2">3. Sesudah jadi baris tagihan, ke mana lagi?</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Satu baris biaya tidak berhenti di tagihan. Ia muncul di <strong>enam tempat</strong>,
                            dan semuanya harus ikut disesuaikan tiap kali ada pos biaya baru — kalau satu
                            terlewat, uangnya hilang tanpa jejak.
                        </p>

                        @php
                            $hilir = [
                                ['Tab Administrasi', 'rincian per jenis biaya, bisa dilihat petugas'],
                                ['Kasir', 'ikut Total Tagihan yang dibayar pasien'],
                                ['Transfer antar unit', 'ikut pindah kalau pasien dipindah RJ▶UGD▶RI'],
                                ['Kwitansi', 'tercetak sebagai baris rincian untuk pasien'],
                                ['Jurnal / pembukuan', 'diakui sebagai pendapatan, lawan piutang'],
                                ['Laporan manajemen', 'Pendapatan RS, Piutang Pasien, gaji dokter'],
                            ];
                        @endphp

                        <div class="grid gap-3 mb-6" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr))">
                            @foreach ($hilir as $i => [$nama, $ket])
                                <div class="ds-card-outline" style="padding:12px 14px">
                                    <div class="flex items-baseline gap-2">
                                        <span class="ds-code" style="color:var(--primary); font-size:12px">{{ $i + 1 }}</span>
                                        <span class="text-sm font-semibold" style="color:var(--ink)">{{ $nama }}</span>
                                    </div>
                                    <p class="mt-1 text-xs" style="color:var(--muted)">{{ $ket }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px; border-color:#d97706">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Saldo kas tidak ikut bergeser.</strong> Halaman Cek Saldo Kas menghitung
                                dari <em>uang yang benar-benar diterima</em> di cabang pembayaran — bukan dari
                                susunan pos biayanya. Yang bergerak saat pos biaya berubah adalah
                                <strong>pendapatan</strong> dan <strong>piutang</strong>.
                            </span>
                        </div>

                        {{-- ===== 4. STATUS PER MODUL ===== --}}
                        <h2 class="ds-title-lg mb-2">4. Ringkasan status</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="w-full" style="font-size:13px; border-collapse:collapse">
                                <thead>
                                    <tr style="background:var(--surface-card)">
                                        <th class="px-4 py-2 text-left" style="color:var(--muted)">Modul</th>
                                        <th class="px-4 py-2 text-left" style="color:var(--muted)">Antrean sendiri?</th>
                                        <th class="px-4 py-2 text-left" style="color:var(--muted)">Perlu ditransfer?</th>
                                        <th class="px-4 py-2 text-left" style="color:var(--muted)">Pengaman pulang?</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ([
                                        ['Laborat', 'Ya', 'Ya — tombol Selesai', 'Ya'],
                                        ['Kamar Operasi', 'Ya', 'Ya — tombol Trf Biaya', 'Ya'],
                                        ['Radiologi', 'Ya (upload hasil)', 'Tidak — langsung', 'Tidak perlu'],
                                        ['Apotek', 'Ya (antrian resep)', 'Tidak — lewat resep', 'Tidak perlu'],
                                    ] as $baris)
                                        <tr style="border-top:1px solid var(--hairline)">
                                            @foreach ($baris as $kolomKe => $isi)
                                                <td class="px-4 py-2" style="color:{{ $kolomKe === 0 ? 'var(--ink)' : 'var(--body)' }}; {{ $kolomKe === 0 ? 'font-weight:600' : '' }}">{{ $isi }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Detail teknis — nama tabel &amp; kunci relasi</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['peta-modul'] }}</pre>
                        </div>
                    </section>


                    {{-- ====== MENAMBAH POS BIAYA BARU ====== --}}
                    <section x-show="section === 'hilir-biaya'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Menambah Pos Biaya Baru</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Menambah satu jenis biaya <strong>bukan pekerjaan satu-dua berkas</strong>. Satu baris
                            biaya muncul di enam lapis, dan melewatkan satu lapis berarti uangnya hilang tanpa
                            jejak — bukan error yang kelihatan. Daftar di bawah disusun dari kejadian nyata saat
                            pos Kamar Operasi dibuka untuk RJ/UGD (2026-08-01).
                        </p>

                        <div class="ds-card-dark" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Enam lapis hilir + cara menyisirnya</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['hilir-biaya'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-8 mb-2">Yang benar-benar terjadi</h2>
                        <div class="space-y-3">
                            @foreach ([
                                ['calculateRICosts() terlewat saat sapuan', 'Ekspresi nvl()-nya ditulis rapat tanpa spasi, sedangkan tempat lain berspasi — grep satu pola meleset. Akibatnya biaya operasi yang dibawa dari RJ/UGD tak masuk kwitansi RI.'],
                                ['Kwitansi RJ/UGD tidak dihitung di PHP', 'Rinciannya dibaca dari view RSVIEW_RJSTRS / RSVIEW_UGDSTRS. Menambah pos di PHP saja membuat kasir menagih lebih besar daripada yang tercetak di kwitansi.'],
                                ['Jurnal bolong Rp 936.000 per operasi', 'Cabang OK di TKVIEW_ACCOUNTS membaca rihdr_no, yang untuk RJ/UGD memang NULL. Kasir menagih penuh & pembayaran mengkredit piutang penuh, tapi pendapatannya tak pernah diakui.'],
                                ['Kolom tetap di tabel transfer', 'rstxn_*tempadmins memetakan biaya ke kolom TETAP. Tanpa kolom baru, biaya yang sudah masuk tagihan hilang begitu pasien dipindah unit.'],
                                ['View jurnal membengkak 26jt → 44jt baris', 'Cabang lama menerbitkan 1 baris per kunjungan walau nol. Cabang baru wajib memakai EXISTS ke tabel sumbernya.'],
                                ['Nomor RJ beririsan dengan nomor UGD', 'Label jurnal hanya memuat nomor kunjungan, dan mis. 203858 ada di kedua tabel. Menguji jurnal harus berbasis SELISIH sebelum-sesudah, bukan total.'],
                            ] as $i => [$judul, $isi])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="flex items-start gap-3">
                                        <span style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:24px;border-radius:9999px;background:var(--primary);color:#fff;font-size:12px;font-weight:700;flex:none">{{ $i + 1 }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{{ $isi }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px" >
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Cara menguji yang terbukti:</strong> jalankan alur penuh di dalam
                                <code class="ds-code">DB::beginTransaction()</code> … <code class="ds-code">DB::rollBack()</code>,
                                lalu bandingkan tiga angka yang harus sama — nilai transfer, subtotal kwitansi,
                                dan kenaikan pendapatan di jurnal. Untuk view besar, bangkitkan definisi barunya
                                dari <code class="ds-code">user_views.text</code> dan validasi lewat view bayangan
                                <code class="ds-code">ZZ_UJI_*</code> sebelum menyentuh yang asli.
                            </span>
                        </div>
                    </section>