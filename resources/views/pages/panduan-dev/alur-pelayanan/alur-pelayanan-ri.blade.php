                    {{-- ====== RI 1 — MASUK ====== --}}
                    <section x-show="section === 'ri-daftar'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 1</div>
                        <h1 class="ds-display-md mb-4">Masuk RI (Pendaftaran)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Alur RI beda watak dari RJ/UGD: bukan satu kali lewat, melainkan
                            <strong>berhari-hari dan berulang</strong>. Semua berawal di
                            <strong>Daftar Rawat Inap</strong> (<span class="ds-code">/ri/daftar</span>) —
                            halaman pusat kerja RI yang diakses hampir semua role klinis.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => null, 'judul' => 'Asal pasien', 'sub' => 'transfer UGD · rujukan poli · langsung'],
                                ['chip' => 'Mr/Tu', 'judul' => 'Pendaftaran RI', 'sub' => 'cara masuk · dokter penerima', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Tempati kamar', 'sub' => 'bangsal · kamar (bed internal)'],
                                ['chip' => 'BPJS', 'judul' => 'SEP RI + SPRI', 'sub' => 'DPJP sinkron', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tiga pintu masuk</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-md space-y-3" style="list-style:disc; padding-left:1.2em">
                                <li><strong>Transfer dari UGD</strong> — paling umum. Modal transfer dua sisi:
                                    perawat UGD mengirim (pilih kamar + penjaminan), perawat ruangan menerima;
                                    biaya UGD ikut terbawa.</li>
                                <li><strong>Rujukan poli (RJ)</strong> — dokter poli memutuskan rawat inap.</li>
                                <li><strong>Daftar langsung</strong> — pasien datang membawa surat/rencana MRS.</li>
                            </ul>
                            <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">
                                Cara masuk tercatat dari master cara masuk (rujukan / UGD / langsung) — dipakai
                                pelaporan & klaim.
                            </p>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Menu pendukung RI</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Menu (URL)</th>
                                            <th>Role</th>
                                            <th>Fungsi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Daftar Rawat Inap — /ri/daftar', 'hampir semua role klinis + admin', 'Pendaftaran, kamar, EMR, dokumen, administrasi — pusat kerja RI'],
                                            ['Gizi Rawat Inap — /ri/gizi', 'Gizi, Admin, Manager', 'Program diet harian + rekap porsi dapur + etiket diet'],
                                            ['PTO — /ri/pto', 'Apoteker', 'Pemantauan seluruh terapi obat pasien RI'],
                                            ['Sinkronisasi Tempat Tidur — /ri/update-tt-ri', 'Admin, Mr, Perawat, Dokter', 'Ketersediaan TT → Aplicares BPJS & SIRS'],
                                        ] as [$menu, $role, $fungsi])
                                            <tr>
                                                <td class="ds-td-strong">{{ $menu }}</td>
                                                <td class="ds-td-meta">{{ $role }}</td>
                                                <td>{{ $fungsi }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Pasien BPJS butuh dua surat: <strong>SEP rawat inap</strong> (penjaminan) dan
                                <strong>SPRI</strong> (surat perintah rawat inap) — DPJP di SEP dijaga sinkron
                                dengan dokter kontrol di SPRI. Satu perawatan = satu baris
                                <span class="ds-code">rstxn_rihdrs</span> + JSON
                                <span class="ds-code">datadaftarri_json</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== RI 2 — EMR HARIAN ====== --}}
                    <section x-show="section === 'ri-emr'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 2</div>
                        <h1 class="ds-display-md mb-4">EMR RI Harian</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            EMR RI adalah <strong>dokumen hidup</strong>: diisi banyak PPA (dokter, perawat,
                            apoteker, gizi, fisioterapis) setiap shift, selama berhari-hari. Dibuka dari tombol
                            <strong>Rekam Medis</strong> di Daftar RI.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => '≤24 jam', 'judul' => 'Pengkajian awal', 'sub' => 'perawat + dokter', 'chipWarna' => 'amber'],
                                ['chip' => 'tiap shift', 'judul' => 'CPPT & SBAR', 'sub' => 'semua PPA · review DPJP', 'chipWarna' => 'sky'],
                                ['chip' => 'harian', 'judul' => 'Visite & konsul', 'sub' => 'DPJP · dokter konsul', 'chipWarna' => 'sky'],
                                ['chip' => 'harian', 'judul' => 'Askep & penilaian', 'sub' => 'SDKI/SLKI/SIKI · risiko', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Order & dokumen', 'sub' => 'lab · rad · consent · edukasi'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Isi EMR RI</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Bagian</th>
                                            <th>Sifat</th>
                                            <th>Isi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Pengkajian Awal', 'sekali, ≤24 jam', 'Perawat + dokter: anamnesis, fisik, leveling DPJP (Utama/Rawat Gabung)'],
                                            ['CPPT', 'multi-entri harian', 'Catatan terintegrasi semua PPA (tab per profesi), di-review & TTD DPJP'],
                                            ['SBAR', 'multi-entri', 'Komunikasi antar shift/PPA'],
                                            ['Asuhan Keperawatan', 'harian', 'Diagnosa SDKI + luaran SLKI + intervensi SIKI'],
                                            ['Penilaian', 'berulang', 'Nyeri, risiko jatuh, dekubitus (Braden), gizi + program diet, C-SSRS'],
                                            ['Pemeriksaan & Order', 'sesuai kebutuhan', 'TTV + order Lab/Radiologi (cabang sama dgn RJ)'],
                                            ['Observasi & Obat', 'harian', 'Monitoring + administrasi pemberian obat'],
                                            ['Modul Dokumen', 'sesuai kebutuhan', 'Consent, edukasi terintegrasi, transfer, surveilans HAIs, akhir hayat, dll.'],
                                            ['E-Resep', 'per lembar', 'Resep harian — lihat tahap Apotek RI'],
                                        ] as [$bagian, $sifat, $isi])
                                            <tr>
                                                <td class="ds-td-token">{{ $bagian }}</td>
                                                <td class="ds-td-meta">{{ $sifat }}</td>
                                                <td>{{ $isi }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Karena banyak tangan menulis bersamaan, aturan mainnya ketat: entri CPPT hanya
                                bisa di-edit pemiliknya (hapus oleh supervisor), review/TTD oleh DPJP Utama,
                                dan semua aksi penting tercatat di <strong>Log Aktivitas</strong> kunjungan.
                            </span>
                        </div>
                    </section>

                    {{-- ====== RI 3 — APOTEK ====== --}}
                    <section x-show="section === 'ri-apotek'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 3</div>
                        <h1 class="ds-display-md mb-4">Apotek RI (per lembar resep)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Beda kunci dari RJ/UGD: e-resep RI ditulis <strong>per lembar</strong> — satu
                            perawatan bisa punya banyak lembar resep (resep hari ke-1, ke-2, resep pulang…).
                            Setiap lembar berjalan sendiri di <strong>Antrian Apotek RI</strong>
                            (<span class="ds-code">/transaksi/ri-resep/antrian-ri-resep</span>).
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'Dokter', 'judul' => 'Tulis lembar resep', 'sub' => 'di EMR — bisa tiap hari', 'chipWarna' => 'sky'],
                                ['chip' => 'per lembar', 'judul' => 'Antrian Apotek RI', 'sub' => 'telaah resep & obat'],
                                ['chip' => null, 'judul' => 'Administrasi obat', 'sub' => 'harga menempel ke lembar'],
                                ['chip' => 'opsional', 'judul' => 'Kasir apotek', 'sub' => 'bayar duluan — kwitansi RESEP LUNAS', 'chipWarna' => 'amber'],
                                ['chip' => null, 'judul' => 'Obat ke ruangan', 'sub' => 'diberikan perawat', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Yang khas di farmasi RI</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-md space-y-3" style="list-style:disc; padding-left:1.2em">
                                <li><strong>Stempel antrean BPJS per lembar</strong> — taskId 6/7 hidup di
                                    tiap lembar resep, bukan di kunjungan.</li>
                                <li><strong>Kasir apotek RI</strong> — lembar resep bisa dilunasi duluan
                                    (kwitansi "RESEP LUNAS") tanpa menunggu pasien pulang; sisanya ikut
                                    tagihan akhir.</li>
                                <li><strong>PTO</strong> (<span class="ds-code">/ri/pto</span>) — apoteker
                                    memantau seluruh terapi obat pasien lintas lembar tanpa mengganggu
                                    e-resep dokter.</li>
                                <li><strong>Bon resep &amp; obat pinjam</strong> punya pos biayanya sendiri;
                                    retur obat menjadi pengurang (Rtn Obat).</li>
                            </ul>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Pemberian obat di ruangan dicatat perawat di EMR (administrasi obat) —
                                terpisah dari pelayanan lembar resep oleh apotek.
                            </span>
                        </div>
                    </section>

                    {{-- ====== RI 4 — ADMINISTRASI & PULANG ====== --}}
                    <section x-show="section === 'ri-administrasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 4</div>
                        <h1 class="ds-display-md mb-4">Administrasi &amp; Pasien Pulang</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Selama dirawat, biaya menumpuk otomatis di layar <strong>Administrasi RI</strong>
                            (dibuka dari Daftar RI). Saat dokter menyatakan boleh pulang, perawatan ditutup —
                            dengan beberapa penjaga agar tidak ada biaya yang tertinggal.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'harian', 'judul' => 'Biaya menumpuk', 'sub' => 'kamar · visite · konsul · obat'],
                                ['chip' => null, 'judul' => 'Pindah kamar?', 'sub' => 'riwayat kamar → tarif per hari'],
                                ['chip' => 'Dokter', 'judul' => 'Boleh pulang', 'sub' => 'resume / ringkasan pulang', 'chipWarna' => 'sky'],
                                ['chip' => 'guard', 'judul' => 'Cek gantungan', 'sub' => 'lab pending · OK belum transfer', 'chipWarna' => 'amber'],
                                ['chip' => 'ri_status P', 'judul' => 'Perawatan ditutup', 'sub' => 'EMR terkunci', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Pos biaya Administrasi RI</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="flex flex-wrap gap-2">
                                @foreach (['Kamar (room + service + perawatan)', 'Visit', 'Konsul', 'Jasa Medis', 'Jasa Dokter', 'Laborat', 'Radiologi', 'Operasi (OK)', 'Bon Resep', 'Obat Pinjam', 'Rtn Obat (pengurang)', 'Trf UGD/RJ', 'Admin RI', 'Admin Usia 14+', 'Lain-lain'] as $pos)
                                    <span class="px-2.5 py-1 text-sm rounded-full"
                                        style="background:var(--surface-card); color:var(--body)">{{ $pos }}</span>
                                @endforeach
                            </div>
                            <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">
                                Semua terisi otomatis dari modulnya masing-masing; "Trf UGD/RJ" membawa biaya
                                kunjungan asal saat pasien ditransfer. Tarif kamar & visite/konsul bisa
                                dikoreksi inline oleh role berwenang — setiap koreksi tercatat di audit log.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
                            <div class="ds-card-outline" style="padding:0;overflow:hidden">
                                <div class="overflow-x-auto">
                                    <table class="ds-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2">ri_status (perawatan)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ([['I', 'Dirawat (inap)'], ['P', 'Pulang'], ['F', 'Batal']] as [$kode, $arti])
                                                <tr>
                                                    <td class="ds-td-token">{{ $kode }}</td>
                                                    <td>{{ $arti }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <span class="ds-spike" style="vertical-align:middle"></span>
                                <span class="ds-body-sm" style="color:var(--body-strong)">
                                    Guard pulang: pemulangan <strong>ditahan sistem</strong> bila masih ada
                                    pemeriksaan <strong>lab berstatus pending</strong> atau transaksi
                                    <strong>Kamar Operasi yang biayanya belum ditransfer</strong> — mencegah
                                    tagihan bocor karena transfer biaya mensyaratkan pasien masih dirawat.
                                </span>
                            </div>
                        </div>
                    </section>

                    {{-- ====== RI 5 — KASIR ====== --}}
                    <section x-show="section === 'ri-kasir'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 5</div>
                        <h1 class="ds-display-md mb-4">Kasir RI</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Petugas Tu memakai <strong>Antrian Kasir RI</strong> dan <strong>Daftar Pasien
                            RI</strong> (<span class="ds-code">/transaksi/kasir/*</span>) untuk menutup
                            tagihan perawatan — termasuk menerima <strong>titipan/deposit</strong> selama
                            pasien masih dirawat.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'selama dirawat', 'judul' => 'Terima titipan', 'sub' => 'deposit mengurangi sisa', 'chipWarna' => 'amber'],
                                ['chip' => null, 'judul' => 'Pasien pulang', 'sub' => 'tagihan final terbentuk'],
                                ['chip' => 'kasir', 'judul' => 'Pembayaran', 'sub' => 'tunai/transfer · kwitansi', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Lunas / piutang', 'sub' => 'sisa masuk monitoring piutang', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Catatan kasir RI</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-md space-y-3" style="list-style:disc; padding-left:1.2em">
                                <li>Pasien BPJS murni: tagihan ditanggung penjamin — kasir menutup dengan
                                    selisih 0 (naik kelas / iur biaya dibayar pasien).</li>
                                <li>Lembar resep yang sudah dilunasi di kasir apotek (RESEP LUNAS) tidak
                                    tertagih dua kali.</li>
                                <li>Sisa tagihan yang tidak terbayar saat pulang masuk
                                    <strong>monitoring piutang pasien</strong> — ditagih/diangsur belakangan
                                    lewat modul Pembayaran Piutang.</li>
                            </ul>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Setelah lunas, pembayaran membentuk jurnal kas di modul keuangan — sama
                                seperti RJ/UGD, hanya nilainya akumulasi seluruh perawatan.
                            </span>
                        </div>
                    </section>