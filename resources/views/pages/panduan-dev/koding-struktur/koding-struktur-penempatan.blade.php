                    {{-- ====== 06 PENEMPATAN PER AREA ====== --}}
                    <section x-show="section === 'area'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Penempatan</div>
                        <h1 class="ds-display-md mb-4">Penempatan per Area</h1>

                        <h2 class="ds-title-lg mb-4">pages/master/ — sudah baku</h2>
                        <p class="ds-body-md mb-8" style="max-width:62ch">
                            Ikuti <span class="ds-code">koding-master</span> apa adanya:
                            <span class="ds-code">master-&lt;nama&gt;/</span> + 2 berkas
                            <span class="ds-code">⚡</span> (list &amp; actions). Acuan kanonik
                            <span class="ds-code">master-agama</span>.
                        </p>

                        <h2 class="ds-title-lg mt-10 mb-4">pages/transaksi/&lt;jalur&gt;/ — jalur pelayanan</h2>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['jalur-transaksi'] }}</pre>
                        </div>
                        <p class="ds-body-sm mb-8" style="max-width:62ch; color:var(--body)">
                            Satu berkas per pos biaya di <span class="ds-code">administrasi-*</span> adalah pola
                            yang <strong>benar</strong> dan dipertahankan — pos biaya tumbuh terus, dan tiap pos
                            punya tarif + audit log sendiri.
                        </p>

                        <h2 class="ds-title-lg mt-10 mb-4">pages/manajemen/ — laporan</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua pola hidup berdampingan dan <strong>keduanya sah</strong>, dengan batas tegas:
                        </p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Pola</th><th>Kapan</th><th>Contoh</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="ds-td-class">manajemen/&lt;modul&gt;/</td>
                                        <td>laporan lintas unit / hub dashboard</td>
                                        <td class="ds-td-class">indikator-pelayanan, mutasi-obat</td>
                                    </tr>
                                    <tr>
                                        <td class="ds-td-class">manajemen/&lt;sumber&gt;/&lt;unit&gt;/&lt;modul&gt;/</td>
                                        <td>laporan yang <strong>format &amp; regulatornya</strong> menentukan bentuk</td>
                                        <td class="ds-td-class">manajemen/sirs/ri/laporan-rl-3-2-rawat-inap</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="ds-body-sm mb-8" style="max-width:66ch; color:var(--body)">
                            Level <span class="ds-code">&lt;sumber&gt;</span> hanya boleh salah satu dari daftar
                            tertutup: <span class="ds-code">rs</span>, <span class="ds-code">sirs</span>,
                            <span class="ds-code">vclaim</span>. <span class="ds-code">&lt;unit&gt;</span>:
                            <span class="ds-code">rj</span>, <span class="ds-code">ugd</span>,
                            <span class="ds-code">ri</span>, <span class="ds-code">penunjang</span>,
                            <span class="ds-code">tu</span>. Ini <strong>satu-satunya</strong> tempat kedalaman 5
                            diizinkan — pengecualian resmi atas P6, karena penamaan RL sudah ditentukan Kemkes.
                        </p>

                        <h2 class="ds-title-lg mt-10 mb-4">pages/components/ — komponen lintas-jalur</h2>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Isinya: cetakan (<span class="ds-code">-print</span> + pembungkus
                            <span class="ds-code">cetak-*</span>), viewer dokumen
                            (<span class="ds-code">*-view-&lt;jalur&gt;</span>), dan modal yang dipanggil dari
                            beberapa layar sekaligus.
                        </p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">pages/components/&lt;domain&gt;/&lt;jalur&gt;/&lt;modul&gt;/&lt;berkas&gt;.blade.php

  domain : modul-dokumen | rekam-medis | manajemen
  jalur  : bpjs | ri | rj | ugd    (atau kelompok fungsi: etiket, penunjang)</pre>
                        </div>
                        <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                            <p class="ds-body-sm" style="margin:0">
                                Sebuah berkas naik ke sini <strong>hanya</strong> kalau pemakainya lebih dari satu
                                area (P3). Kalau cuma dipakai satu layar, ia tinggal di folder modulnya.
                            </p>
                        </div>
                    </section>

                    {{-- ====== 07 BATAS UKURAN & CARA PECAH ====== --}}
                    <section x-show="section === 'ukuran'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Penempatan</div>
                        <h1 class="ds-display-md mb-4">Batas Ukuran &amp; Cara Pecah</h1>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Jenis</th><th>Ideal</th><th>Wajib pecah di</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">LIST / layar utama</td><td>≤ 300 baris</td><td>&gt; 600</td></tr>
                                    <tr><td class="ds-td-strong">-actions (form/modal)</td><td>≤ 400 baris</td><td>&gt; 800</td></tr>
                                    <tr><td class="ds-td-strong">-print</td><td>≤ 500 baris</td><td>&gt; 900</td></tr>
                                    <tr><td class="ds-td-strong">Trait / class Support</td><td>≤ 400 baris</td><td>&gt; 700</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mb-4">Caranya: partial per section, bukan komponen anak</h2>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['pecah-partial'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Dua syarat batas partial — wajib dicek dua-duanya</h2>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['rekonstruksi'] }}</pre>
                        </div>

                        <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Batas ini hanya untuk MARKUP</div>
                            <p class="ds-body-sm" style="margin:0">
                                Berkas yang besar karena <strong>blok kelas Volt</strong>-nya tidak terbantu oleh
                                pemecahan partial. Contoh: <span class="ds-code">⚡daftar-rj-actions</span> —
                                1.330 dari 1.679 barisnya adalah kelas. Yang perlu dikurangi kelasnya
                                (pisah ke trait/Support), dan itu keputusan desain per modul, layak dikerjakan
                                saat modul itu disentuh.
                            </p>
                        </div>
                    </section>
