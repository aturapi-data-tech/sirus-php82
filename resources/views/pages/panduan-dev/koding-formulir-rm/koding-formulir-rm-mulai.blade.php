                    {{-- ====== 01 PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Pengkodean Formulir Rekam Medis</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Setiap cetakan SIRUS yang menjadi bagian rekam medis pasien membawa
                            <strong>kode formulir</strong> kecil di pojok kiri bawah setiap halaman, misalnya
                            <span class="ds-code">RM-02.01 · Rev.0</span>. Dari kode itu petugas rekam medis
                            dan surveior bisa tahu lembar apa ini, rancangan versi berapa, dan menelusurinya
                            ke Daftar Induk.
                        </p>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            <strong>Setiap formulir cetak baru wajib diberi kode</strong> sebelum di-merge —
                            kecuali cetakan yang memang bukan formulir rekam medis.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mb-8 md:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--primary)">Dasar akreditasi</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• <strong>MRMIK 3 butir h</strong> — identifikasi &amp; pelacakan dokumen yang beredar (judul, edisi/revisi)</li>
                                    <li>• <strong>MRMIK 6</strong> — standardisasi &amp; identifikasi formulir rekam medis</li>
                                    <li>• <strong>MRMIK 6.c</strong> — formulir dievaluasi &amp; diperbaharui; nomor revisi jadi jejaknya</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-caption-up mb-2" style="color:var(--muted-soft)">Dua tempat yang terlibat</div>
                                <ul class="ds-body-sm space-y-1.5">
                                    <li>• prop <span class="ds-code">kode="RM-02.01 · Rev.0"</span> di tag layout blade cetak — dicetak apa adanya</li>
                                    <li>• halaman ini — daftar kelompok &amp; nama formulir; view dan revisi dibaca dari blade</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 02 ATURAN KODE ====== --}}
                    <section x-show="section === 'aturan'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Aturan Kode</h1>

                        <div class="ds-card-outline mb-8" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">anatomi kode</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snippet['anatomi'] }}</pre>
                        </div>

                        @foreach ([
                            ['A1', 'Satu formulir, satu kode — di semua jalur.',
                             'General Consent RJ, UGD, dan RI adalah formulir yang sama, jadi ketiga blade cetaknya memasang kode yang sama (RM-02.01). Kode baru hanya bila isi formulirnya memang berbeda.'],
                            ['A2', 'Kode hanya untuk formulir rekam medis.',
                             'Kuitansi, etiket, SEP/PRB/SKDP BPJS, slip gaji, dan dokumen unit IT tidak dikode. Semuanya tampil di "Cetakan Tanpa Kode" — pastikan tak ada formulir rekam medis yang ikut nyasar di sana.'],
                            ['A3', 'Kelompok mengikuti isi rekam medis, bukan jalur atau unit.',
                             'Laporan Operasi masuk 05 (Pembedahan) di mana pun ia dicetak. Kelompok baru hanya bila tak ada satu pun dari 11 kelompok yang cocok.'],
                            ['A4', 'Kode tidak pernah dipakai ulang.',
                             'Formulir yang dihentikan ditandai nonaktif di daftar, barisnya tetap. Rekam medis lama yang tercetak dengan kode itu harus tetap bisa ditelusuri.'],
                            ['A5', 'Revisi naik saat RANCANGAN berubah — di semua jalur sekaligus.',
                             'Butir isian ditambah, diubah, atau dihapus. Perbaikan tampilan dan bug tidak menaikkan revisi.'],
                            ['A6', 'Kode ditulis literal dan lengkap di blade.',
                             'kode="RM-KK.NN · Rev.N" — bukan :kode="…", bukan tanpa revisi. Daftar Formulir RM hanya membaca bentuk itu.'],
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
                    </section>
