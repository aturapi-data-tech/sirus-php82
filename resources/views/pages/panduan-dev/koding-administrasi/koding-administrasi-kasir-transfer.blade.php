                    {{-- ====== 05 KASIR ====== --}}
                    <section x-show="section === 'kasir'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Administrasi</div>
                        <h1 class="ds-display-md mb-4">Alur Kasir sampai Pasien Pulang</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Urutan baku administrasi: set tanggal pulang → input bayar → proses pulang.
                            Setelah pulang, form terkunci dan hanya menyisakan tombol batal.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Alur postTransaksi (proses pulang)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['kasir'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">LUNAS vs BON</div>
                                <div class="ds-body-sm"><span class="ds-code">status_pulang</span>: <strong>'L'</strong> (LUNAS) bila bayar ≥ sisa tagihan; <strong>'H'</strong> (BON/Hutang) bila kurang — sisa jadi piutang pasien.</div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Terkunci setelah pulang</div>
                                <div class="ds-body-sm"><span class="ds-code">isFormLocked</span> = true saat status Pulang → input disable, muncul banner + tombol <strong>Batal Transaksi</strong>.</div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 06 TRANSFER ====== --}}
                    <section x-show="section === 'transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Transfer &amp; Batal</div>
                        <h1 class="ds-display-md mb-4">Transfer antar-layanan</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua arah: <strong>RJ → UGD</strong> dan <strong>UGD → RI</strong>. Polanya sama —
                            buat header tujuan, pindahkan biaya asal lewat tabel <span class="ds-code">tempadmins</span>,
                            kunci kunjungan asal.
                        </p>

                        <h2 class="ds-title-md mt-6 mb-3">Transfer UGD → RI</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Pasien UGD yang perlu dirawat inap di-<em>transfer</em>: sistem membuat header RI baru,
                            memindahkan biaya UGD/RJ ke RI, dan mengunci UGD. Komponen:
                            <span class="ds-code">transfer-ugd-ke-ri-actions</span>.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Transfer — tabel & tautan yang ditulis</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transfer'] }}</pre>
                        </div>

                        <h2 class="ds-title-md mt-8 mb-3">Transfer RJ → UGD</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Arah sebaliknya, pola sama. Komponen:
                            <span class="ds-code">transfer-rj-ke-ugd-actions</span>.
                        </p>
                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">RJ → UGD &amp; penamaan komponen</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transfer-rj-ugd'] }}</pre>
                        </div>

                        <h2 class="ds-title-md mt-8 mb-3">Dokter &amp; tarif saat transfer</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Bagian yang paling sering salah: dokter &amp; tarif <strong>tidak boleh disalin mentah</strong>
                            dari kunjungan asal. Tiga jebakan di bawah semuanya pernah terjadi di produksi.
                        </p>
                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Aturan dokter, tarif &amp; admin</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['transfer-dokter-tarif'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-3">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Penerima ≠ DPJP</div>
                                <div class="ds-body-sm">
                                    <span class="ds-code">rihdr.dr_id</span> = dokter <strong>Penerima</strong>.
                                    DPJP ada di <span class="ds-code">levelingDokter</span> (EMR, bisa lebih dari satu).
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">poli_price = tarif UGD</div>
                                <div class="ds-body-sm">
                                    Di <span class="ds-code">rstxn_ugdhdrs</span> kolom itu diisi dari
                                    <span class="ds-code">ugd_price</span>, bukan tarif poli. Nama kolom menyesatkan.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">admin_status = nominal</div>
                                <div class="ds-body-sm">
                                    Bukan flag. <span class="ds-code">par_id=2</span> = 50.000, dijumlahkan sebagai uang.
                                    Menulis <span class="ds-code">'1'</span> = menagih Rp 1.
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Tautan UGD↔RI yang andal = baris <span class="ds-code">rstxn_ritempadmins</span> flag 'UGD'</strong>
                                (<span class="ds-code">tempadm_ref=rj_no → rihdr_no</span>), bukan <span class="ds-code">rstxn_ribiayaselamadugds</span>
                                yang bisa kosong untuk data lama Oracle Dev 6i. (Lihat bab Batal Transfer.)
                            </span>
                        </div>
                    </section>