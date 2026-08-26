                    {{-- ====== 07 BATAL TRANSFER ====== --}}
                    <section x-show="section === 'batal-transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Transfer</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan transfer UGD→RI: menghapus RI yang baru dibuat &amp; mengembalikan UGD ke Aktif.
                            Hanya boleh bila RI <strong>belum diproses</strong> &amp; <strong>belum ada transaksi</strong>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalTransferRI — cari RI berlapis + guard + aksi</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-transfer'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Bug yang diperbaiki:</strong> dulu pengecekan hanya melihat
                                <span class="ds-code">rstxn_ribiayaselamadugds</span> → transfer lama (tanpa baris itu)
                                salah dianggap "Tidak ada data transfer". Fix: cari <span class="ds-code">rihdr_no</span>
                                dari <span class="ds-code">rstxn_ritempadmins</span> (link utama) dulu, baru fallback.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 08 BATAL TRANSAKSI ====== --}}
                    <section x-show="section === 'batal-transaksi'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Transaksi (Pembayaran / Pulang)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan <strong>pembayaran</strong>, bukan admisi. Menghapus payment &amp; mengembalikan
                            status ke sebelum-bayar. Ada di ketiga jalur (kasir-rj / kasir-ugd / kasir-ri).
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalTransaksi (contoh RI)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-transaksi'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                RI: Pulang ('P') → Dirawat ('I'). RJ/UGD: reset field pembayaran &amp; buka kembali status.
                                Ini <strong>bukan</strong> pembatalan admisi — untuk itu lihat bab <em>Batal Inap</em>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 09 BATAL INAP ====== --}}
                    <section x-show="section === 'batal-inap'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Inap → status F</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Membatalkan <strong>pendaftaran inap</strong> yang salah/tak jadi. Bersifat
                            <strong>soft</strong> (set <span class="ds-code">ri_status='F'</span>, record tetap),
                            hanya boleh saat masih Dirawat, bukan dari transfer, dan belum ada transaksi apa pun.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalInap — guard bertingkat + set 'F'</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-inap'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Kenapa soft (set 'F'), bukan hapus? Karena laporan sudah mengecualikan 'F' &amp; jejak
                                audit harus terjaga. Bed dibebaskan (<span class="ds-code">trfroom end_date=SYSDATE</span>)
                                &amp; pasien di-unlock agar bisa didaftar ulang.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 10 MATRIKS ====== --}}
                    {{-- ====== 10 BATAL TRANSAKSI APOTEK RI (SLS) ====== --}}
                    <section x-show="section === 'batal-sls'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Batal Transaksi Apotek RI (SLS)</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Membatalkan <strong>pembayaran resep rawat inap</strong>. Header resep ada di
                            <span class="ds-code">imtxn_slshdrs</span> — bukan <span class="ds-code">rstxn_*hdrs</span>
                            seperti RJ/UGD/RI — dan statusnya cuma dua:
                            <span class="ds-code">'A'</span> belum diproses kasir, <span class="ds-code">'L'</span> sudah.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">batalTransaksi (Apotek RI) — guard, anti-race, efek samping</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['batal-sls'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Dua komponen kembar.</strong>
                                <span class="ds-code">administrasi-ri-resep</span> (dibuka dari Antrian RI-Resep di
                                halaman Apotek) dan <span class="ds-code">administrasi-kasir-ri</span> (dari Antrian
                                Kasir RI) punya judul modal yang sama persis tapi file &amp; nama event berbeda.
                                Rolenya pun beda: Apoteker|Admin|Tu vs Admin|Manager Umum|Supervisor Tu. Kalau
                                menyalin pola dari satu ke yang lain, cek ulang nama event LOV-nya.
                            </span>
                        </div>
                    </section>