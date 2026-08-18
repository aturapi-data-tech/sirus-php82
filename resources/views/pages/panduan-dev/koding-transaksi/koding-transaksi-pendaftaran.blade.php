                    {{-- ====== 04 PENDAFTARAN ====== --}}
                    <section x-show="section === 'pendaftaran'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">Pendaftaran</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Halaman <span class="ds-code">daftar-&lt;jalur&gt;</span> = list harian +
                            form pendaftaran sebagai <strong>modal actions terpisah</strong>
                            (pola sama dengan master, tapi jauh lebih kaya). Acuan:
                            <span class="ds-code">transaksi/rj/daftar-rj/⚡daftar-rj-actions.blade.php</span>.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead><tr><th>Bagian form</th><th>Pola yang dipakai</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Pilih pasien</td><td class="ds-body-sm"><span class="ds-code">lov-pasien</span> (target unik) + <span class="ds-code">MasterPasienTrait::findDataMasterPasien(regNo)</span>; link ke Master Pasien utk pasien baru</td></tr>
                                    <tr><td class="ds-td-strong">Pilih dokter/poli</td><td class="ds-body-sm">LOV dokter-poli — jadwal &amp; kuota</td></tr>
                                    <tr><td class="ds-td-strong">Klaim / penjamin</td><td class="ds-body-sm">BPJS vs UMUM (<span class="ds-code">klaim_status</span>); SEP via modal VClaim terpisah (<span class="ds-code">vclaim-rj-actions</span>)</td></tr>
                                    <tr><td class="ds-td-strong">Antrean BPJS</td><td class="ds-body-sm">no antrian + task-id (AntrianTrait) disimpan di JSON <span class="ds-code">taskIdPelayanan</span>; booking MJKN dijemput dari <span class="ds-code">referensi_mobilejkn_bpjs</span></td></tr>
                                    <tr><td class="ds-td-strong">Cetak</td><td class="ds-body-sm">etiket pasien (print-agent localhost:9999), SEP, berkas BPJS</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-dark mt-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kerangka save pendaftaran — nomor, antrian, guard, status awal</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['pendaftaran-save'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Status baris list dihitung dari task-id di JSON</strong>
                                (taskId 3–7, 99=batal), bukan kolom status tersendiri —
                                jadi mengubah status = menulis JSON (lewat pola lock bab 03), bukan UPDATE kolom.
                            </span>
                        </div>

                        <p class="ds-body-md mt-6" style="max-width:62ch">
                            Panggilan API BPJS (SEP, antrean, dsb) <strong>wajib ber-timeout</strong>
                            (<span class="ds-code">timeout(8)-&gt;connectTimeout(3)</span>) — panggilan sinkron
                            tanpa timeout pernah membekukan worker seluruh aplikasi.
                        </p>
                    </section>

                    {{-- ====== 05 LIST & PERFORMA ====== --}}
                    <section x-show="section === 'list'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">List Transaksi &amp; Performa</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            List transaksi berbeda dari list master: datanya jutaan baris riwayat,
                            tiap baris membawa CLOB JSON besar, dan ada subquery penunjang (lab/rad).
                            Tiga aturan performa di bawah ini <strong>tidak boleh dilewati</strong> —
                            semuanya lahir dari list yang pernah lemot di produksi.
                        </p>

                        @php
                            $listBadge = 'display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:9999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;line-height:1;flex:none';
                        @endphp

                        {{-- visual anatomi list transaksi --}}
                        <div class="ds-frame mt-2 mb-2">
                            <div class="ds-frame-label">Tata letak list transaksi (daftar-rj / antrian-*)</div>
                            <div class="mt-3" style="border:1px solid var(--hairline); border-radius:14px; overflow:hidden; background:var(--canvas)">

                                {{-- toolbar --}}
                                <div class="flex flex-wrap items-center gap-2 px-4 py-3" style="position:relative; background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                    <div style="height:34px;padding:8px 12px;border-radius:8px;border:1px solid var(--hairline);background:var(--canvas);color:var(--muted-soft);font-size:13px;display:flex;align-items:center;font-family:var(--mono)">10/07/2026 📅</div>
                                    <div style="height:34px;padding:8px 12px;border-radius:8px;border:1px solid var(--hairline);background:var(--canvas);color:var(--muted-soft);font-size:13px;display:flex;align-items:center;width:160px">Cari pasien...</div>
                                    <span class="ds-btn ds-btn-primary" style="height:34px; padding:6px 12px; font-size:12px">+ Daftar Baru</span>
                                    <span style="{{ $listBadge }};position:absolute;top:8px;right:8px">1</span>
                                </div>

                                {{-- baris pasien --}}
                                <div class="px-4 py-3" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span>
                                            <span class="block text-sm"><span style="font-family:var(--mono); color:var(--primary)">012345</span> · <strong style="color:var(--ink)">FULANAH</strong> <span style="color:var(--muted)">(P)</span></span>
                                            <span class="block text-xs" style="color:var(--muted)">01/01/1990 (36 th) · JL. MAWAR NO. 1, TULUNGAGUNG</span>
                                        </span>
                                        <span class="flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-full" style="background:var(--success-tint); color:var(--success-deep)">Task 5 · Dilayani</span>
                                            <span class="ds-caption" style="color:var(--muted)">EMR · SEP · Adm</span>
                                        </span>
                                    </div>
                                    <span style="{{ $listBadge }};position:absolute;top:8px;right:8px;background:var(--info)">2</span>
                                </div>

                                {{-- pagination + poll --}}
                                <div class="flex items-center justify-between px-4 py-2.5" style="position:relative; background:var(--surface-soft)">
                                    <span class="ds-caption" style="color:var(--muted)">Menampilkan 1–10 dari 128 kunjungan</span>
                                    <span class="ds-caption" style="color:var(--muted)">‹ 1 2 3 ›</span>
                                    <span style="{{ $listBadge }};position:absolute;top:8px;right:8px">3</span>
                                </div>
                            </div>
                        </div>

                        {{-- legenda list transaksi --}}
                        <div class="grid grid-cols-1 gap-2 mb-6 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Toolbar — filter TANGGAL (default hari ini; kunci utama scope query) + cari + tombol Daftar; antrian kasir/apotek menambah wire:poll.30s', ''],
                                ['2', 'Baris pasien — identitas standar list: No RM · nama (gender) · tgl lahir (umur, dihitung dari birth_date) · alamat; badge status DIHITUNG dari task-id di JSON (3–7, 99 = batal); tombol aksi membuka modal (EMR/SEP/Administrasi)', 'background:var(--info)'],
                                ['3', 'Pagination DB-level — paginate() di query, decode CLOB hanya utk page aktif via transform()', ''],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $listBadge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola query list — ⚡daftar-rj.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['list-query'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-6 sm:grid-cols-3">
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Scope subquery</div>
                                <div class="ds-body-sm">Subquery lab/rad JOIN ke header + filter tanggal — jangan biarkan full-scan.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Decode per-page</div>
                                <div class="ds-body-sm">OracleLob + json_decode hanya di <span class="ds-code">transform()</span> page aktif (±10 baris), bukan di query.</div>
                            </div>
                            <div class="ds-card" style="padding:20px">
                                <div class="ds-title-sm mb-1">Poll seperlunya</div>
                                <div class="ds-body-sm">Antrian (kasir/apotek) <span class="ds-code">wire:poll.30s</span>; halaman pendaftaran TIDAK perlu poll.</div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                <strong>Render versioning granular:</strong> pakai
                                <span class="ds-code">WithRenderVersioningTrait</span> per-area (toolbar, modal) supaya
                                ganti filter tidak me-remount seluruh halaman — dan JANGAN
                                <span class="ds-code">incrementVersion</span> saat user mengetik di search (fokus hilang).
                                Lookup list (dokter/poli) buat stabil: hanya depend tanggal, bukan semua filter
                                (<span class="ds-code">docs/stable-lookup-list-pattern.md</span>).
                            </span>
                        </div>

                        <p class="ds-body-md mt-6" style="max-width:62ch">
                            Aksi per baris berbentuk <strong>dropdown</strong> dengan guard role per item
                            (Ubah Pendaftaran = Mr|Admin|Supervisor Tu; Hapus = Admin|Manager Medis|…;
                            Diagnosa EMR = +Casemix|Dokter). Tombol utama (EMR, Dokumen, Administrasi)
                            men-dispatch event ke modal host — list tidak pernah menulis data sendiri.
                        </p>
                    </section>