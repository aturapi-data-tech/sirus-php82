                    {{-- ====== 12 RANJAU ====== --}}
                    <section x-show="section === 'ranjau'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Ranjau Umum</h1>
                        <div class="space-y-3">
                            @foreach ([
                                ['Sumber tautan transfer', 'Jangan andalkan hanya rstxn_ribiayaselamadugds — bisa kosong (data Oracle Dev 6i). Link utama = rstxn_ritempadmins flag UGD.'],
                                ['Selalu lock sebelum tulis', 'lockRJRow/lockUGDRow/lockRIRow di dalam DB::transaction; tanpa lock, dua kasir bisa bentrok (last write wins).'],
                                ['Batal ≠ hapus', 'Batal Inap = SET ri_status F (soft), bukan DELETE. Laporan sudah mengecualikan F; hapus akan merusak audit & nomor.'],
                                ['Guard transaksi sebelum batal', 'Selalu cek RI/UGD belum punya transaksi (visit/obat/lab/dll.) sebelum batal transfer/inap, demi integritas billing.'],
                                ['Bebaskan bed & unlock pasien', 'Batal inap/transfer wajib menutup end_date kamar & mengembalikan lockstatus pasien, agar bed & pasien bisa dipakai lagi.'],
                                ['Audit setiap batal', 'appendAdminLog{RI,RJ,UGD} untuk tiap pembatalan — jejak siapa & kapan.'],
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
                    </section>

                    {{-- ====== 13 GLOSARIUM ====== --}}
                    <section x-show="section === 'glosarium'" x-cloak>
                        <div class="ds-eyebrow mb-3">14 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Glosarium</h1>
                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Istilah</th><th>Arti</th></tr></thead>
                                <tbody>
                                    @foreach ([
                                        ['ri_status', 'Status RI: I=Dirawat, P=Pulang, F=Batal'],
                                        ['rj_status / txn_status', 'Status RJ/UGD: A=Aktif, I=Transfer Inap'],
                                        ['status_pulang', "Cara pulang: 'L'=Lunas, 'H'=Bon/Hutang"],
                                        ['rstxn_ritempadmins', 'Jembatan biaya RI — carry-over biaya UGD/RJ (kolom tempadm_flag). Link utama transfer UGD↔RI'],
                                        ['rstxn_ugdtempadmins', 'Jembatan biaya sementara UGD sebelum transfer'],
                                        ['rstxn_ribiayaselamadugds', 'Tabel link tambahan UGD→RI (rj_no ↔ ugd_no_rsri) — bisa kosong utk data lama'],
                                        ['rsmst_trfrooms', 'Riwayat kamar RI (start_date/end_date) — end_date kosong = bed sedang ditempati'],
                                        ['tempadm_flag', "Penanda asal biaya di ritempadmins: 'UGD' / 'RJ'"],
                                        ['lockstatus', 'Penanda pasien sedang dikunci di satu jalur (UGD/RI) agar tak dobel'],
                                        ['Batal Transaksi', 'Batalkan pembayaran/pulang → status kembali sebelum-bayar'],
                                        ['Batal Transfer', 'Batalkan transfer UGD→RI → RI dihapus, UGD kembali Aktif'],
                                        ['Batal Inap', 'Batalkan admisi RI → ri_status F (soft)'],
                                        ['Bon', 'Pembayaran kurang dari tagihan — sisa jadi piutang pasien'],
                                    ] as [$istilah, $arti])
                                        <tr>
                                            <td class="ds-td-strong" style="white-space:nowrap">{{ $istilah }}</td>
                                            <td class="ds-body-sm">{{ $arti }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>