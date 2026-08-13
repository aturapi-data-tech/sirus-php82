                    {{-- ====== CABANG — KAMAR OPERASI ====== --}}
                    <section x-show="section === 'ok'" x-cloak>
                        <div class="ds-eyebrow mb-3">Cabang dari EMR</div>
                        <h1 class="ds-display-md mb-4">Kamar Operasi (OK)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Cabang penunjang keempat — melayani pasien <strong>RJ, UGD, maupun RI</strong>
                            (transaksi OK menempel ke kunjungan induk lewat jalur + nomor kunjungannya).
                            Dua menu terlibat: <strong>Jadwal Operasi</strong>
                            (<span class="ds-code">/operasi/jadwal-operasi</span> — booking) dan
                            <strong>Transaksi Kamar Operasi</strong>
                            (<span class="ds-code">/transaksi/penunjang/kamar-operasi</span> — pelaksanaan
                            &amp; biaya; role petugas OK / Supervisor Penunjang).
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'booking', 'judul' => 'Jadwal Operasi', 'sub' => 'rencana operasi pasien'],
                                ['chip' => 'A', 'judul' => 'Transaksi OK dibuka', 'sub' => 'pasien masuk kamar operasi', 'chipWarna' => 'amber'],
                                ['chip' => null, 'judul' => 'Tindakan & tarif', 'sub' => 'jenis operasi · kelas'],
                                ['chip' => null, 'judul' => 'Crew & jasa', 'sub' => 'operator · asisten · omloop · oncall'],
                                ['chip' => null, 'judul' => 'Bahan & alat', 'sub' => 'pemakaian BHP'],
                                ['chip' => 'L', 'judul' => 'Trf Biaya → induk', 'sub' => 'pos Operasi (OK) kunjungan', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Status transaksi OK</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Arti</th>
                                            <th>Boleh apa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['A', 'Berjalan', 'Tindakan/crew/bahan masih bisa diisi & diubah'],
                                            ['L', 'Selesai — biaya sudah ditransfer ke kunjungan induk', 'Terkunci; pembatalan transfer (kembali ke A) hanya oleh role berwenang'],
                                        ] as [$kode, $arti, $boleh])
                                            <tr>
                                                <td class="ds-td-token">{{ $kode }}</td>
                                                <td class="ds-td-strong">{{ $arti }}</td>
                                                <td>{{ $boleh }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Kaitannya dengan alur pasien: tombol <strong>Trf Biaya</strong> itulah yang
                                mengisi pos <strong>Kamar Operasi / Operasi (OK)</strong> di administrasi
                                RJ/UGD/RI. Khusus RI, transfer mensyaratkan pasien <strong>masih
                                dirawat</strong> — karena itu pemulangan ditahan sistem selama masih ada
                                transaksi OK berstatus A (lihat guard pulang di seksi Rawat Inap).
                            </span>
                        </div>
                    </section>