                    {{-- ====== 05 MENAMBAH FORMULIR BARU ====== --}}
                    <section x-show="section === 'formulir-baru'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Praktik</div>
                        <h1 class="ds-display-md mb-4">Menambah Formulir Baru</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Dikerjakan bersamaan dengan membuat berkas <span class="ds-code">cetak-…-print.blade.php</span>
                            — bukan belakangan. Nama berkas cetak <strong>wajib berakhiran
                            <span class="ds-code">-print.blade.php</span></strong>; Daftar Formulir RM hanya menyisir pola itu.
                        </p>

                        @foreach ([
                            ['1', 'Putuskan: formulir rekam medis atau bukan?',
                             'Bukan (kuitansi, label, dokumen BPJS/unit) → tak perlu kode, selesai.'],
                            ['2', 'Sudah ada di jalur lain?',
                             'Isi formulirnya sama → pasang kode yang SUDAH ADA (lengkap dengan revisinya) di blade cetak jalur baru. Jangan membuat kode baru untuk hasil port RI ⇄ UGD ⇄ RJ.'],
                            ['3', 'Formulir baru → pilih kelompok, ambil nomor berikutnya.',
                             'Tambahkan satu baris kode → nama di method formulir() halaman ini. Nomor tidak boleh mengisi celah bekas kode nonaktif.'],
                            ['4', 'Pasang prop kode di tag layout blade cetaknya.',
                             'Atribut literal kode="RM-KK.NN · Rev.0".'],
                            ['5', 'Buka Daftar Formulir RM & lihat PDF-nya sekali.',
                             'View cetak baru muncul di baris kodenya; tak ada peringatan "Perlu dibenahi". Kode tampil di pojok kanan atas dan tidak menimpa kop.'],
                        ] as [$nomor, $judul, $keterangan])
                            <div class="ds-card-outline mb-3" style="padding:18px 20px">
                                <div class="flex items-start gap-3">
                                    <span class="ds-code shrink-0" style="color:var(--primary); font-weight:700">{{ $nomor }}</span>
                                    <div>
                                        <div class="ds-body-md" style="font-weight:600; color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-body-sm mt-1" style="color:var(--body)">{{ $keterangan }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <h2 class="ds-title-lg mt-10 mb-4">Blade cetak</h2>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">prop kode</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snippet['blade-baru'] }}</pre>
                        </div>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">tulis literal</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snippet['kode-literal'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Daftar di halaman ini</h2>
                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">formulir baru saja</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snippet['daftar-baru'] }}</pre>
                        </div>
                    </section>

                    {{-- ====== 06 REVISI & NONAKTIF ====== --}}
                    <section x-show="section === 'revisi'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Praktik</div>
                        <h1 class="ds-display-md mb-4">Revisi &amp; Nonaktif</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Nomor revisi adalah jejak <strong>MRMIK 6.c</strong> — bukti formulir dievaluasi dan
                            diperbaharui. Naikkan di commit yang sama dengan perubahan rancangannya, di
                            <strong>semua</strong> blade yang memakai kode itu, dan catat alasannya di pesan commit.
                            Revisi yang berbeda antar jalur ditandai di Daftar Formulir RM.
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">naik revisi</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snippet['revisi'] }}</pre>
                        </div>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">formulir dihentikan</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snippet['nonaktif'] }}</pre>
                        </div>

                        <div class="ds-card-outline" style="padding:20px; border-color:var(--primary)">
                            <div class="ds-caption-up mb-2" style="color:var(--primary)">Revisi formulir ≠ versi klausul</div>
                            <p class="ds-body-sm" style="margin:0">
                                Dokumen bertanda tangan dengan teks legal (consent) juga memakai
                                <span class="ds-code">clause-versioning</span> — record lama dicetak dengan redaksi saat
                                ditandatangani. Keduanya berjalan sendiri-sendiri: klausul berganti versi biasanya
                                ikut menaikkan revisi formulir, tetapi revisi formulir bisa naik tanpa klausul berubah.
                            </p>
                        </div>
                    </section>

                    {{-- ====== 07 CEK & CHECKLIST ====== --}}
                    <section x-show="section === 'pemeriksa'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Praktik</div>
                        <h1 class="ds-display-md mb-4">Cek &amp; Checklist</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Daftar Formulir RM dan Cetakan Tanpa Kode di halaman ini sudah menjadi pemeriksanya.
                            Saat halaman tak bisa dibuka (mis. Oracle mati), cukup grep:
                        </p>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">dari akar repo</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snippet['cek'] }}</pre>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Checklist sebelum merge</h2>
                        <div class="ds-card-outline" style="padding:24px">
                            <ul class="ds-body-sm space-y-2.5">
                                @foreach ([
                                    'Berkas cetak baru berakhiran -print.blade.php',
                                    'Formulir rekam medis → prop kode="RM-KK.NN · Rev.N" terpasang literal di tag layout',
                                    'Formulir baru → baris kode & nama ditambahkan di halaman ini',
                                    'Hasil port jalur lain memakai kode yang sudah ada, bukan kode baru',
                                    'Rancangan berubah → revisi naik di semua jalur, di commit yang sama',
                                    'Tidak ada kode yang dihapus atau dipakai ulang',
                                    'Daftar Formulir RM tanpa peringatan, dan PDF dilihat sekali: kode tak menimpa kop',
                                ] as $butir)
                                    <li class="flex items-start gap-2">
                                        <span style="color:var(--primary)">☐</span>
                                        <span>{{ $butir }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
