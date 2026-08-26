                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Konsep Administrasi &amp; Batal</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            <strong>Administrasi (kasir)</strong> adalah tahap akhir perjalanan pasien:
                            menghitung seluruh pos biaya, memproses pembayaran, dan memulangkan pasien.
                            Tiga jalur — <strong>RJ</strong>, <strong>UGD</strong>, <strong>RI</strong> — polanya mirip
                            tapi tak identik; RI paling kaya (billing per-item, transfer kamar, transfer masuk dari UGD/RJ).
                        </p>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Bab-bab di sini merangkum <strong>model status</strong>, <strong>struktur biaya</strong>,
                            <strong>alur kasir sampai pulang</strong>, serta <strong>tiga model pembatalan</strong>
                            yang sering tertukar: <em>Batal Transaksi</em>, <em>Batal Transfer</em>, dan <em>Batal Inap</em>.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Transaksi</div>
                                <div class="ds-body-sm">Batalkan <strong>pembayaran/pulang</strong>. Status kembali ke sebelum-bayar (RI: Pulang→Dirawat).</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Transfer</div>
                                <div class="ds-body-sm">Batalkan <strong>transfer UGD→RI</strong>. RI dihapus, UGD kembali Aktif ('A').</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Batal Inap</div>
                                <div class="ds-body-sm">Batalkan <strong>admisi RI</strong> → status <span class="ds-code">'F'</span> (soft, record tetap). Hanya bila belum ada transaksi.</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Prinsip semua batal:</strong> jalankan dalam <span class="ds-code">DB::transaction</span> +
                                <span class="ds-code">lock*Row</span>, verifikasi status &amp; guard dulu, tulis audit
                                (<span class="ds-code">appendAdminLog*</span>), dan gate role sesuai aksi
                                (Batal Inap/Kunjungan RI: Admin / Supervisor Tu; Batal Transfer &amp; Transaksi RJ/UGD:
                                Admin, Tu, Perawat, Manager Umum, Supervisor Tu).
                            </span>
                        </div>
                    </section>

                    {{-- ====== 02 STATUS ====== --}}
                    <section x-show="section === 'status'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Model Status Transaksi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Batal = memindahkan status. Kenali dulu kode status tiap jalur.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Jalur</th><th>Kolom</th><th>Nilai</th><th>Arti</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">RJ / UGD</td><td class="ds-td-class">rj_status / txn_status</td><td class="ds-td-class">A</td><td class="ds-body-sm">Aktif / Antri</td></tr>
                                    <tr><td class="ds-body-sm">RJ / UGD</td><td class="ds-td-class">rj_status / txn_status</td><td class="ds-td-class">I</td><td class="ds-body-sm">Transfer Inap (terkunci)</td></tr>
                                    <tr><td class="ds-td-strong">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class" style="color:var(--primary)">I</td><td class="ds-body-sm"><strong>Dirawat</strong> (default admisi)</td></tr>
                                    <tr><td class="ds-body-sm">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class">P</td><td class="ds-body-sm">Pulang (sudah bayar)</td></tr>
                                    <tr><td class="ds-body-sm">RI</td><td class="ds-td-class">ri_status</td><td class="ds-td-class" style="color:#dc2626">F</td><td class="ds-body-sm"><strong>Batal</strong> (dikecualikan laporan)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Ringkasan kode status</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['status'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>ri_status='F' hanya DIBACA</strong> oleh laporan (SIRS RL, manajemen) yang
                                mengecualikannya. Menandai batal = <em>menulis</em> 'F' (soft), bukan menghapus baris —
                                agar jejak audit &amp; statistik tetap konsisten.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 03 BIAYA ====== --}}
                    <section x-show="section === 'biaya'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Struktur Biaya &amp; Total</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Total tagihan = penjumlahan pos biaya dari tabel-tabel transaksi per jalur.
                            Perhitungan dibuat <strong>reusable</strong> supaya kasir &amp; transfer memakai angka yang sama.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola perhitungan biaya (calculateRJCosts)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['biaya'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Tabel jembatan biaya transfer = <span class="ds-code">rstxn_ritempadmins</span></strong>
                                (kolom <span class="ds-code">tempadm_flag</span>). Saat UGD/RJ transfer ke RI, biaya asalnya
                                ikut disalin ke sini (flag 'UGD'/'RJ') supaya total RI mencakup biaya sebelum masuk inap.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px; border-color:#d97706">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Kolomnya TETAP, bukan bebas.</strong>
                                <span class="ds-code">rstxn_*tempadmins</span> menampung biaya per kolom
                                (<span class="ds-code">rj_admin, poli_price, acte_price, actp_price, actd_price,
                                obat, lab, rad, other, rs_admin, ok</span>) — bukan per baris pos. Karena itu
                                menambah komponen di <span class="ds-code">calculate*Costs()</span> tanpa menambah
                                kolomnya di sini membuat angka itu <em>ikut ditagih di kasir tetapi hilang saat
                                pasien dipindah unit</em>. Kolom <span class="ds-code">ok</span> ditambahkan
                                2026-08-01 untuk biaya Kamar Operasi. Lihat bab
                                <strong>Menambah Pos Biaya Baru</strong>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 04 ALUR VISUAL (FLOW) ====== --}}
                    <section x-show="section === 'flow'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Alur Visual</div>
                        <h1 class="ds-display-md mb-4">Alur Visual (Flowchart)</h1>
                        <p class="ds-body-md mb-2" style="max-width:64ch">
                            Bab ini menceritakan <strong>perjalanan seorang pasien</strong> — dari mendaftar,
                            dilayani, sampai pulang &amp; membayar — dengan gambar sederhana. Semua di sini
                            pakai <strong>bahasa sehari-hari</strong>, tanpa istilah teknis.
                        </p>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            Butuh detail kode / nama tabel / aturan teknis? Buka <strong>Sisi 2 — Coding</strong> (tombol di atas).
                        </p>

                        @php
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

                        {{-- ===== 1. TIGA JALUR (RJ / UGD / RI) ===== --}}
                        <h2 class="ds-title-lg mb-2">1. Tiga jenis transaksi (tiga jalur pelayanan)</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Ada <strong>tiga jenis pelayanan</strong>, masing-masing punya alur &amp; kasir sendiri:
                            <strong>Rawat Jalan (RJ)</strong>, <strong>UGD</strong>, dan <strong>Rawat Inap (RI)</strong>.
                        </p>

                        @php
                            $jalurFlows = [
                                ['RJ · Rawat Jalan', 'pasien poli — pulang hari itu juga', [
                                    ['Daftar', 'di loket', 'entry'],
                                    ['Diperiksa Dokter', 'di poli', 'main'],
                                    ['Lab / Rontgen', 'bila perlu', 'opt'],
                                    ['Ambil Obat', 'di apotek', 'main'],
                                    ['Kasir RJ', 'bayar', 'cash'],
                                    ['Pulang', '', 'done'],
                                ]],
                                ['UGD · Gawat Darurat', 'pasien darurat — bisa pulang atau dirawat', [
                                    ['Daftar UGD', 'di IGD', 'entry'],
                                    ['Triase &amp; Ditangani', 'sesuai kegawatan', 'main'],
                                    ['Lab / Rontgen', 'bila perlu', 'opt'],
                                    ['Ambil Obat', 'di apotek', 'main'],
                                    ['Kasir UGD', 'bayar', 'cash'],
                                    ['Pulang', 'atau transfer ke Rawat Inap', 'done'],
                                ]],
                                ['RI · Rawat Inap', 'pasien menginap — biaya dihitung per hari / per-item', [
                                    ['Masuk', 'daftar / transfer', 'entry'],
                                    ['Dirawat', 'visit · obat · lab · tindakan tiap hari', 'main'],
                                    ['Kasir RI', 'dijumlah saat mau pulang', 'cash'],
                                    ['Pulang', 'lunas / bon', 'done'],
                                ]],
                            ];
                        @endphp

                        @foreach ($jalurFlows as [$namaJalur, $ketJalur, $steps])
                            <div class="ds-card-outline mb-4" style="padding:14px 16px">
                                <div class="flex flex-wrap items-baseline gap-x-2 mb-2">
                                    <span class="ds-title-sm" style="color:var(--primary)">{{ $namaJalur }}</span>
                                    <span class="ds-caption" style="color:var(--muted)">— {{ $ketJalur }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach ($steps as $i => [$judul, $ket, $tone])
                                        @if ($i > 0) {!! $arrow !!} @endif
                                        <span class="ds-card-outline" style="{{ $flowBox($tone) }}; background:var(--canvas)">
                                            <span class="block text-sm font-semibold" style="color:var(--ink)">{!! $judul !!}</span>
                                            @if ($ket !== '')<span class="block text-xs" style="color:var(--muted)">{!! $ket !!}</span>@endif
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            <span style="color:#d97706">▦ kotak garis putus-putus</span> = langkah <strong>opsional</strong> (cuma bila perlu lab/rontgen).
                            <strong>RJ &amp; UGD mirip</strong> — selesai di hari yang sama. <strong>RI beda</strong> — pasien
                            menginap, jadi biaya dikumpulkan tiap hari &amp; baru ditotal saat pulang. Ketiganya bisa
                            <strong>lunas</strong> (dibayar penuh) atau <strong>bon</strong> (dibayar sebagian).
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Khusus rawat inap: resep apoteknya transaksi terpisah.</strong>
                                Obat pasien inap ditebus lewat <strong>Apotek RI</strong> yang punya nomor
                                (<span class="ds-code">No SLS</span>), antrean, dan <strong>kasir sendiri</strong> —
                                terpisah dari kasir RI yang menghitung biaya kamar &amp; tindakan saat pulang.
                                Satu pasien inap bisa punya banyak resep. Sisa yang belum dibayar di kasir apotek
                                masuk <strong>Bon Inap</strong>, lalu ditagih saat pasien pulang.
                            </span>
                        </div>

                        {{-- ===== 2. TRANSFER (PINDAH TINGKAT PELAYANAN) ===== --}}
                        <h2 class="ds-title-lg mb-2">2. Kalau pasien dipindah (transfer)</h2>
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            @foreach ([
                                ['Rawat Jalan', 'poli', 'entry'],
                                ['UGD', 'kondisi darurat', 'main'],
                                ['Rawat Inap', 'perlu dirawat', 'main'],
                                ['Kasir', 'biaya semua tahap dijumlah', 'cash'],
                                ['Pulang', '', 'done'],
                            ] as $i => [$judul, $ket, $tone])
                                @if ($i > 0) {!! $arrow !!} @endif
                                <span class="ds-card-outline" style="{{ $flowBox($tone) }}">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $judul }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{!! $ket !!}</span>
                                </span>
                            @endforeach
                        </div>
                        <p class="ds-body-md mb-2" style="max-width:64ch">
                            Kalau kondisi memburuk, pasien bisa <strong>dipindah</strong> ke pelayanan yang lebih tinggi —
                            dari <strong>poli ke UGD</strong>, lalu dari <strong>UGD ke rawat inap</strong>.
                        </p>
                        <div class="ds-card-outline mb-8" style="padding:14px 18px; border-color:#059669">
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                💡 <strong>Yang penting: tagihan ikut pindah.</strong> Anggap saja seperti membawa
                                <strong>keranjang belanja</strong> — isinya tidak berkurang saat pindah kasir. Jadi biaya
                                poli &amp; UGD <strong>otomatis sudah termasuk</strong> saat pasien membayar di rawat inap.
                                Tidak ada biaya yang tertinggal atau hilang.
                            </span>
                        </div>

                        {{-- ===== 3. PEMBATALAN ===== --}}
                        <h2 class="ds-title-lg mb-2">3. Kalau ada yang perlu dibatalkan</h2>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Kadang ada kekeliruan yang harus dikoreksi. Ada <strong>3 cara membatalkan</strong>,
                            dipilih sesuai keadaan pasien:
                        </p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-4">
                            @foreach ([
                                ['Batal Pembayaran', 'Batal Transaksi', 'Pasien sudah terlanjur <strong>dibayar/dipulangkan</strong>, tapi mau dikoreksi. Pembayaran dibatalkan, status kembali seperti <strong>belum bayar</strong> (bisa diproses ulang).'],
                                ['Batal Kunjungan', 'Batal Kunjungan / Inap', 'Pasien terlanjur <strong>didaftarkan</strong> tapi ternyata batal datang / salah input. Ditandai <strong>"Batal"</strong> — datanya <strong>tidak dihapus</strong>, cuma dicap batal (jejak tetap ada).'],
                                ['Batal Perpindahan', 'Batal Transfer', 'Pemindahan (mis. UGD → rawat inap) ternyata keliru. Perpindahan dibatalkan, <strong>tagihan dikembalikan</strong> ke tempat asal, tempat asal aktif lagi.'],
                            ] as [$tag, $judul, $ket])
                                <div class="ds-card-outline" style="padding:16px 18px; border-color:#dc2626">
                                    <div class="ds-caption-up mb-1" style="color:#dc2626">{{ $tag }}</div>
                                    <div class="ds-title-sm mb-2">{{ $judul }}</div>
                                    <div class="ds-body-sm">{!! $ket !!}</div>
                                </div>
                            @endforeach
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            Kalau pasien sudah pulang lalu ingin dibatalkan total: <strong>batalkan pembayaran dulu</strong>
                            (kembali aktif), <strong>baru batalkan kunjungannya</strong>. Untuk pasien hasil pindahan,
                            gunakan <strong>Batal Perpindahan</strong>, bukan Batal Kunjungan.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:16px 20px; border-color:#d97706">
                            <div class="ds-caption-up mb-1" style="color:#d97706">Resep Apotek RI</div>
                            <div class="ds-body-sm" style="color:var(--body-strong)">
                                Untuk resep rawat inap, yang bisa dibatalkan <strong>baru pembayarannya</strong> —
                                resep kembali berstatus belum diproses kasir dan bisa dibayar ulang.
                                <strong>Resepnya sendiri belum bisa dibatalkan.</strong> Kalau resep terlanjur salah
                                dan belum dibayar, obatnya harus dihapus satu per satu; nomor resepnya tetap ada dan
                                tetap muncul di antrean apotek. Ini beda dari kunjungan RJ/UGD/RI yang sudah punya
                                <strong>Batal Kunjungan</strong>.
                            </div>
                        </div>

                        {{-- ===== ATURAN MAIN (guards dalam bahasa awam) ===== --}}
                        <h2 class="ds-title-lg mb-3">Aturan main biar data tetap rapi</h2>
                        <div class="space-y-3 mb-4">
                            @foreach ([
                                ['🧪', 'Belum bisa dibayar/dipulangkan kalau hasil lab belum keluar.', 'Supaya tagihan pasti sudah lengkap sebelum pasien membayar. Berlaku di rawat jalan, UGD, maupun rawat inap.'],
                                ['🧹', 'Membatalkan hanya boleh kalau BELUM ada tindakan, obat, atau pembayaran.', 'Kalau pasien sudah dilayani (ada obat/lab/tindakan), tak bisa asal dibatalkan — biar tidak ada biaya yang hilang begitu saja.'],
                                ['📌', 'Membatalkan = memberi cap "Batal", bukan menghapus.', 'Datanya tetap tersimpan untuk audit. Laporan otomatis mengeluarkan data yang berstatus Batal, jadi tidak ikut dihitung.'],
                                ['🔒', 'Yang boleh membatalkan dibatasi sesuai jenisnya.', 'Batal inap rawat inap hanya Admin / Supervisor TU; batal transfer & transaksi di RJ/UGD juga boleh TU, Perawat, dan Manager Umum. Petugas lain tidak bisa — mencegah salah/sengaja hapus.'],
                                ['📱', 'Batal antrean BPJS (di Mobile JKN) itu urusan terpisah.', 'Itu hanya untuk melapor ke BPJS, dan TIDAK mengubah tagihan/status di sistem kita. Dua hal yang berbeda.'],
                            ] as [$emoji, $judul, $ket])
                                <div class="ds-card-outline" style="padding:14px 18px">
                                    <div class="flex items-start gap-3">
                                        <span style="font-size:20px; line-height:1.2">{{ $emoji }}</span>
                                        <div>
                                            <div class="ds-title-sm mb-1">{{ $judul }}</div>
                                            <div class="ds-body-sm">{{ $ket }}</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px; border-color:var(--primary)">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Ingin tahu <strong>bagaimana ini dikerjakan di kode</strong> — nama tombol, tabel database,
                                &amp; kode status? Semua ada di <strong>Sisi 2 — Coding</strong> (tombol di bagian atas halaman).
                            </span>
                        </div>
                    </section>