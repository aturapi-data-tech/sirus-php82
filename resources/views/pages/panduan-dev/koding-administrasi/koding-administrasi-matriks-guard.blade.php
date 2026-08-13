                    <section x-show="section === 'matriks'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Matriks Model Batal</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Tiga model batal sering tertukar. Bedakan dari <strong>apa yang dibatalkan</strong> &amp;
                            <strong>status akhirnya</strong>.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Model</th><th>Membatalkan</th><th>Status: dari → ke</th><th>Guard utama</th><th>Role</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Batal Transaksi</td><td class="ds-body-sm">Pembayaran / pulang</td><td class="ds-td-class">P → I (RI) · reset (RJ/UGD)</td><td class="ds-body-sm">Sudah dibayar/pulang</td><td class="ds-body-sm">Admin / Supervisor Tu</td></tr>
                                    <tr><td class="ds-td-strong">Batal Transfer</td><td class="ds-body-sm">Transfer UGD→RI</td><td class="ds-td-class">UGD: I → A · RI dihapus</td><td class="ds-body-sm">RI belum ada transaksi; lab UGD tak pending</td><td class="ds-body-sm">Admin / Tu</td></tr>
                                    <tr><td class="ds-td-strong">Batal Inap</td><td class="ds-body-sm">Admisi RI</td><td class="ds-td-class">I → F (soft)</td><td class="ds-body-sm">Dirawat, bukan transfer, belum ada transaksi</td><td class="ds-body-sm">Admin / Supervisor Tu</td></tr>
                                    <tr><td class="ds-td-strong">Batal Transaksi (Apotek RI)</td><td class="ds-body-sm">Pembayaran resep RI</td><td class="ds-td-class">L → A (<span class="ds-code">imtxn_slshdrs.status</span>)</td><td class="ds-body-sm">Status 'L'; pasien belum pulang; <span class="ds-code">lockForUpdate</span> + baca ulang status</td><td class="ds-body-sm">Apoteker / Admin / Tu <span class="ds-body-sm" style="color:var(--muted)">(kasir-ri: Admin / Manager Umum / Supervisor Tu)</span></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Apotek RI belum punya lapis kedua.</strong>
                                <span class="ds-code">administrasi-ri-resep</span> &amp;
                                <span class="ds-code">administrasi-kasir-ri</span> hanya punya Batal Transaksi
                                (pembayaran). Resep yang terlanjur salah dan belum dibayar harus dihapus obat per obat
                                lewat <span class="ds-code">removeObat()</span> — header
                                <span class="ds-code">imtxn_slshdrs</span> tetap ada dan tetap muncul di antrian.
                                Kalau lapis kedua ditambahkan, ikuti pola Batal Inap: soft-cancel ke status batal,
                                role lebih ketat, syarat belum ada pembayaran.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-3" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Sesudah batal, samakan properti dengan yang ditulis ke DB — jangan di-null-kan.</strong>
                                <span class="ds-code">batalTransaksi()</span> RJ/UGD dulu men-set
                                <span class="ds-code">$txnStatus = null</span> padahal DB ditulis
                                <span class="ds-code">'A'</span>. Tombol Batal Transaksi (A → F) digerbangi
                                <span class="ds-code">$txnStatus === 'A'</span>, jadi tombolnya hilang sesudah
                                Post → Batal sampai modal ditutup &amp; dibuka ulang. Diperbaiki di
                                <span class="ds-code">91218d91</span>. Buang juga cache computed
                                (<span class="ds-code">unset($this-&gt;isKasirPosted, ...)</span>) dan
                                <span class="ds-code">emp_id</span> sengaja TIDAK direset demi jejak audit.
                            </span>
                        </div>

                        <div class="ds-card-outline mt-3" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Urutan bila kasus campur:</strong> pasien sudah pulang lalu ingin dibatalkan total →
                                (1) Batal Transaksi (P→I), lalu (2) Batal Inap (I→F). Pasien dari UGD → gunakan
                                Batal Transfer, bukan Batal Inap.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 11 GUARD & KONSISTENSI TRANSFER ====== --}}
                    <section x-show="section === 'guard-transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Guard &amp; Konsistensi Transfer</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Checklist semua <strong>guard</strong> di dua alur transfer
                            (<span class="ds-code">RJ→UGD</span> &amp; <span class="ds-code">UGD→RI</span>),
                            saat <strong>create (maju)</strong> maupun <strong>batal (mundur)</strong>,
                            plus status <strong>konsistensi</strong> antar-arah.
                        </p>

                        {{-- ===== GUARD CREATE ===== --}}
                        <h2 class="ds-title-lg mb-3">A. Guard saat CREATE (maju)</h2>
                        <div class="ds-card-outline mb-3" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Guard</th><th>Pesan / arti</th><th>Berlaku</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">rjNo ada</td><td class="ds-body-sm">"Data transaksi tidak ditemukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Sumber status 'A'</td><td class="ds-body-sm">"sudah diproses, tidak bisa ditransfer"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lab tidak pending</td><td class="ds-body-sm">"Hasil Laborat belum selesai, transfer tidak bisa dilakukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Kamar Operasi tidak pending</td><td class="ds-body-sm"><span class="ds-code">checkOkPending{RJ,UGD}</span> — pesannya menyebut nomor OK-nya. Transfer mengubah <span class="ds-code">rj_status</span> jadi 'I', padahal Trf Biaya mensyaratkan 'A'</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Belum pernah transfer</td><td class="ds-body-sm">idempoten (cek <span class="ds-code">*biayaselamadi*</span>) — "sudah pernah dilakukan"</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Anti-race</td><td class="ds-body-sm">"Data sudah diproses oleh user lain" (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Data sumber ada</td><td class="ds-body-sm">"Data UGD/RJ tidak ditemukan" (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Pasien lockstatus</td><td class="ds-body-sm">"Pasien sedang dalam status X, tidak bisa transfer" (cegah dobel jalur)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Ruangan dipilih</td><td class="ds-body-sm">wajib pilih room</td><td class="ds-body-sm" style="color:var(--primary)">UGD→RI saja</td></tr>
                                    <tr><td class="ds-td-strong">Bed dipilih</td><td class="ds-body-sm">"Pilih ruangan dan bed terlebih dahulu"</td><td class="ds-body-sm" style="color:var(--primary)">UGD→RI saja</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-caption mb-8" style="color:var(--muted)">
                            CREATE <strong>sudah konsisten</strong> di kedua arah — kecuali UGD→RI menambah pilih room/bed (memang butuh tempat tidur).
                        </p>

                        {{-- ===== GUARD BATAL ===== --}}
                        <h2 class="ds-title-lg mb-3">B. Guard saat BATAL (mundur)</h2>
                        <div class="ds-card-outline mb-3" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Guard</th><th>Arti</th><th>Berlaku</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Role Admin | Tu</td><td class="ds-body-sm">hanya Admin/TU boleh batal transfer</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">rjNo ada</td><td class="ds-body-sm">data transaksi ditemukan</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lookup target</td><td class="ds-body-sm">cari header hasil transfer</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Target bisa dibatalkan</td><td class="ds-body-sm">UGD→RI: RI harus 'I'; RJ→UGD: UGD harus 'A'</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Target belum ada transaksi</td><td class="ds-body-sm">obat/lab/rad/tindakan/jasa/lain-lain + pembayaran</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Sumber status 'I'</td><td class="ds-body-sm">memang tertransfer (dalam transaksi)</td><td class="ds-body-sm">keduanya</td></tr>
                                    <tr><td class="ds-td-strong">Lab-pending DILEPAS</td><td class="ds-body-sm" style="color:var(--primary)">batal (mundur) TIDAK diblok lab pending</td><td class="ds-body-sm">keduanya ✅</td></tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- ===== KONSISTENSI ===== --}}
                        <h2 class="ds-title-lg mt-8 mb-3">C. Konsistensi antar-arah (batal)</h2>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Aspek</th><th>UGD→RI (kuat)</th><th>RJ→UGD (tertinggal)</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Lookup transfer</td><td class="ds-body-sm" style="color:var(--primary)">berlapis (ritempadmins + fallback)</td><td class="ds-body-sm" style="color:#d97706">1 sumber (ugdbiayaselamadirjs) ⚠️</td></tr>
                                    <tr><td class="ds-td-strong">Not-found → recovery</td><td class="ds-body-sm" style="color:var(--primary)">✅ UGD 'I'→'A'</td><td class="ds-body-sm" style="color:#dc2626">❌ tak ada — RJ bisa nyangkut 'I'</td></tr>
                                    <tr><td class="ds-td-strong">Header target saat batal</td><td class="ds-body-sm" style="color:var(--primary)">soft ri_status='F'</td><td class="ds-body-sm" style="color:#d97706">hard delete ugdhdrs (rawan ORA-02292) ⚠️</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Verdict:</strong> guard CREATE &amp; guard inti BATAL sudah konsisten.
                                Yang <strong>belum</strong>: batal <span class="ds-code">RJ→UGD</span> perlu (1) lookup berlapis via
                                <span class="ds-code">ugdtempadmins</span> flag 'RJ', (2) recovery RJ 'I'→'A' saat data tak ketemu.
                                Poin (3) hard-delete <strong>tak bisa 100% sama</strong> karena UGD tak punya status 'F' seperti RI —
                                opsi: buat delete berpanduan child atau biarkan.
                            </span>
                        </div>
                    </section>

                    {{-- ====== EDIT INLINE TABEL BIAYA ====== --}}
                    <section x-show="section === 'edit-inline'" x-cloak>
                        <div class="ds-eyebrow mb-3">05b — Administrasi</div>
                        <h1 class="ds-display-md mb-4">Edit Inline Tabel Biaya</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Sel tabel yang tersimpan begitu blur — dipakai di Riwayat Kamar
                            (<span class="ds-code">room-ri</span>: Hari, tarif kamar/perawatan/CS, tanggal
                            Mulai &amp; Selesai) serta tabel Visit/Konsul. Yang berbahaya di sini bukan UI-nya,
                            melainkan <strong>angka biaya yang ikut bergerak</strong>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kerangka aksi — urutannya mengikat</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['edit-inline'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Kolom turunan = tiru rumus pembuatnya</div>
                                <div class="ds-body-sm">
                                    <span class="ds-code">day</span> mengalikan biaya
                                    (<span class="ds-code">subtotal = (kamar+prwtn+cs) × day</span>). Pindah Kamar menulisnya
                                    <span class="ds-code">max(1, ROUND(trfrDate - start_date))</span>, maka aksi lain wajib sama.
                                    Beda rumus antar-jalur = hasil berbeda tergantung user masuk lewat pintu mana.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Log tiap kolom pengali biaya</div>
                                <div class="ds-body-sm">
                                    Cek aksi lama di file yang sama — sempat timpang: hapus kamar &amp; ubah tarif ter-log,
                                    tapi <span class="ds-code">updateDay</span> tidak. Log tulis <strong>lama → baru</strong>;
                                    kolom NULL tulis maknanya (<span class="ds-code">(otomatis)</span>), bukan <span class="ds-code">0</span>.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Guard state, bukan cuma format</div>
                                <div class="ds-body-sm">
                                    Selesai tak boleh lebih kecil dari Mulai (sama persis boleh — kamar transit);
                                    Selesai dikosongkan = kamar aktif lagi, tolak bila sudah ada baris aktif lain.
                                    Bandingkan pasangan nilai final, supaya edit Mulai kena aturan yang sama.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">UI</div>
                                <div class="ds-body-sm">
                                    Ring fokus brand (samakan dengan <span class="ds-code">x-text-input</span>), tombol hapus baris
                                    = outline merah-tint + ikon sampah <span class="ds-code">!px-2 !py-1</span>. Saat terkunci,
                                    sel kembali jadi teks biasa — bukan sekadar <span class="ds-code">disabled</span>.
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Sengaja belum dipasang</strong> di Riwayat Kamar (keputusan user — jangan ditambahkan diam-diam):
                                guard tumpang tindih antar baris, rentang di luar tanggal rawat inap, dan sinkronisasi rantai transfer
                                (Selesai baris bawah ↔ Mulai baris atas).
                            </span>
                        </div>
                    </section>