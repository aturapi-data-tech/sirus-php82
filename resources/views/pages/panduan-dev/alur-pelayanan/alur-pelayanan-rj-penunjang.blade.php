                    {{-- ====== RJ — STATUS & TASKID ====== --}}
                    <section x-show="section === 'rj-status'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Jalan — Ringkasan</div>
                        <h1 class="ds-display-md mb-4">Siklus Status &amp; taskId</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Dua status berjalan sendiri-sendiri — jangan tertukar: status
                            <strong>pendaftaran</strong> (<span class="ds-code">rj_status</span>, dilihat petugas
                            pendaftaran &amp; kasir) dan status <strong>pelayanan EMR</strong>
                            (<span class="ds-code">erm_status</span>, dilihat dokter/perawat).
                        </p>

                        <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
                            <div class="ds-card-outline" style="padding:0;overflow:hidden">
                                <div class="overflow-x-auto">
                                    <table class="ds-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2">rj_status (pendaftaran)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ([
                                                ['A', 'Antrian — terdaftar, belum ditutup kasir'],
                                                ['L', 'Selesai — sudah dibayar/ditutup'],
                                                ['F', 'Batal'],
                                                ['I', 'Transfer ke UGD/RI — kunjungan pindah jalur'],
                                            ] as [$kode, $arti])
                                                <tr>
                                                    <td class="ds-td-token">{{ $kode }}</td>
                                                    <td>{{ $arti }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:0;overflow:hidden">
                                <div class="overflow-x-auto">
                                    <table class="ds-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2">erm_status (pelayanan EMR)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ([['A', 'Belum Dilayani'], ['L', 'Selesai dilayani dokter/perawat']] as [$kode, $arti])
                                                <tr>
                                                    <td class="ds-td-token">{{ $kode }}</td>
                                                    <td>{{ $arti }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Garis waktu taskId (waktu tunggu BPJS = selisih antar-stempel)</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => '1', 'judul' => 'Admisi daftar', 'sub' => 'Daftar RJ'],
                                ['chip' => '2', 'judul' => 'Dipanggil', 'sub' => 'Daftar RJ'],
                                ['chip' => '3', 'judul' => 'Daftar poli', 'sub' => 'Daftar/Booking'],
                                ['chip' => '4', 'judul' => 'Masuk poli', 'sub' => 'Pelayanan'],
                                ['chip' => '5', 'judul' => 'Keluar poli', 'sub' => 'Pelayanan'],
                                ['chip' => '6', 'judul' => 'Masuk apotek', 'sub' => 'Apotek'],
                                ['chip' => '7', 'judul' => 'Obat diterima', 'sub' => 'Apotek', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Stempel antrean BPJS (taskId) di sepanjang alur</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>taskId</th>
                                            <th>Arti</th>
                                            <th>Tahap / siapa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['1', 'Admisi mendaftarkan pasien', 'Daftar RJ — pendaftaran'],
                                            ['2', 'Pasien dipanggil admisi', 'Daftar RJ — pendaftaran'],
                                            ['3', 'Pasien daftar poli', 'Daftar RJ / Booking RJ'],
                                            ['4', 'Pasien masuk poli', 'Pelayanan RJ — poli'],
                                            ['5', 'Pasien keluar poli', 'Pelayanan RJ — poli'],
                                            ['6', 'Pasien masuk apotek', 'Apotek RJ'],
                                            ['7', 'Obat diserahkan ke pasien', 'Apotek RJ'],
                                            ['99', 'Pembatalan', 'Daftar RJ — batal'],
                                        ] as [$task, $arti, $tahap])
                                            <tr>
                                                <td class="ds-td-token">{{ $task }}</td>
                                                <td class="ds-td-strong">{{ $arti }}</td>
                                                <td class="ds-td-meta">{{ $tahap }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Selisih antar-stempel = waktu tunggu yang dinilai BPJS. Stempel yang telat atau
                                dobel bukan kosmetik — semua titik memakai pola idempoten (set hanya bila kosong,
                                kirim ulang hanya bila belum 200/208). Detail teknis: skill
                                <span class="ds-code">bpjs-antrean-task-id</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== CABANG — LABORAT ====== --}}
                    <section x-show="section === 'rj-laborat'" x-cloak>
                        <div class="ds-eyebrow mb-3">Cabang dari EMR</div>
                        <h1 class="ds-display-md mb-4">Order Lab → Modul Laborat</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Saat dokter mengirim <strong>order laborat</strong> dari tab Pemeriksaan EMR
                            (Diagnosis/Keterangan Klinis wajib diisi), order itu <em>tidak selesai di EMR</em> —
                            ia berpindah tangan ke program terpisah milik petugas lab:
                            <strong>Transaksi Laboratorium</strong> (<span class="ds-code">/transaksi/penunjang/laborat</span>,
                            role Laboratorium / Supervisor Penunjang). Satu order = satu nomor pemeriksaan
                            (<span class="ds-code">checkup_no</span>) yang melayani RJ, UGD, maupun RI.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'EMR', 'judul' => 'Order lab dikirim', 'sub' => 'Ket. Klinis wajib', 'chipWarna' => 'sky'],
                                ['chip' => 'P', 'judul' => 'Antrian lab', 'sub' => 'menunggu sampel', 'chipWarna' => 'amber'],
                                ['chip' => 'C', 'judul' => 'Proses', 'sub' => 'etiket spesimen · input hasil / Mindray', 'chipWarna' => 'sky'],
                                ['chip' => 'H', 'judul' => 'Hasil selesai', 'sub' => 'tampil di EMR + cetak PDF', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Siklus status pemeriksaan lab</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Arti</th>
                                            <th>Yang terjadi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['P', 'Pendaftaran / antrian', 'Order dari EMR masuk list petugas lab; sampel belum diambil'],
                                            ['C', 'Proses', 'Sampel diambil (cetak etiket spesimen), pemeriksaan berjalan, hasil mulai diinput'],
                                            ['H', 'Hasil / Selesai', 'Hasil final — tampil di EMR dokter & bisa dicetak PDF; biaya menempel ke kunjungan induk'],
                                            ['F', 'Batal', 'Pendaftaran dibatalkan (hanya Admin / Supervisor Penunjang)'],
                                        ] as [$kode, $arti, $jadi])
                                            <tr>
                                                <td class="ds-td-token">{{ $kode }}</td>
                                                <td class="ds-td-strong">{{ $arti }}</td>
                                                <td>{{ $jadi }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Langkah petugas lab</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li>Order muncul di list (filter default hari ini) → <strong>proses</strong> +
                                    cetak <strong>etiket spesimen</strong>.</li>
                                <li>Input hasil per item — manual, atau <strong>tarik otomatis dari alat
                                    Mindray</strong>. Tiap hasil dibandingkan rentang normal per gender
                                    (flag H/L/N) dan <strong>ambang nilai kritis</strong> (badge kritis).</li>
                                <li>Isi <strong>Kesimpulan</strong>, lalu tandai <strong>Selesai (H)</strong> —
                                    hasil langsung terbaca dokter di EMR (display + cetak PDF).</li>
                                <li>Hasil <strong>lab rujukan/luar</strong>: upload PDF-nya lewat menu
                                    <em>Upload Hasil Lab Luar</em>.</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Rel administrasinya: biaya lab menempel otomatis ke kunjungan induk, dan tab
                                Laboratorium di layar Administrasi bersifat <strong>read-only</strong> —
                                kelola/batal hanya dari modul lab (batal = eskalasi Admin/Supervisor
                                Penunjang, bukan petugas lab sendiri).
                            </span>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Proses order end-to-end: EMR → Modul Lab → kembali ke EMR</div>
                        @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/14-order-lab.png', 'caption' => 'TAHAP 1 · EMR RJ — dokter membuat Order Pemeriksaan Laboratorium: pilih item dari katalog, toggle CITO bila mendesak, isi Diagnosis/Keterangan Klinis (wajib) → Kirim Order.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/17-tab-penunjang.png', 'caption' => 'TAHAP 1b · EMR RJ — order tercatat di tab Pelayanan Penunjang kunjungan dengan statusnya; dokter bisa memantau tanpa meninggalkan EMR.'],
                            ['src' => 'images/panduan-dev/alur/laborat/01-list.png', 'caption' => 'TAHAP 2 · Modul Lab — order otomatis muncul di worklist Transaksi Laboratorium (satu antrian lintas RJ/UGD/RI, badge jalur + status Terdaftar/Proses/Selesai; keterangan klinis dokter ikut tampil).'],
                            ['src' => 'images/panduan-dev/alur/laborat/02-detail-terdaftar.png', 'caption' => 'TAHAP 3 · Fase Administrasi (Terdaftar) — petugas membuka detail: identitas, dokter pengirim, klinis, Ref No kunjungan induk; item masih bisa ditambah/dihapus. Cetak Etiket spesimen → klik Proses Administrasi.'],
                            ['src' => 'images/panduan-dev/alur/laborat/03-item-terpilih.png', 'caption' => 'TAHAP 3b — paket (mis. Hematologi 3 Diff) otomatis membawa sub-itemnya; total pemeriksaan terhitung.'],
                            ['src' => 'images/panduan-dev/alur/laborat/04-pemeriksaan-luar.png', 'caption' => 'TAHAP 3c (opsional) — tab Pemeriksaan Luar untuk rujukan lab luar (deskripsi + tarif) dalam transaksi yang sama.'],
                            ['src' => 'images/panduan-dev/alur/laborat/05-obat-bahan.png', 'caption' => 'TAHAP 3d (opsional) — tab Obat dan Bahan untuk BHP lab yang dibebankan ke transaksi.'],
                            ['src' => 'images/panduan-dev/alur/laborat/06-input-hasil.png', 'caption' => 'TAHAP 4 · Fase Proses — entry hasil per item atau Import Hasil Mindray (tarik otomatis dari alat). Nilai lewat ambang langsung tersorot: LEUKOSIT 17.3 → Tinggi + badge KRITIS merah.'],
                            ['src' => 'images/panduan-dev/alur/laborat/07-flag-otomatis.png', 'caption' => 'TAHAP 4b — flag Tinggi/Rendah dihitung otomatis dari rentang normal per item (per gender); petugas cukup mengisi angka.'],
                            ['src' => 'images/panduan-dev/alur/laborat/08-kesimpulan.png', 'caption' => 'TAHAP 4c — isi Kesimpulan (tersimpan otomatis) → Simpan Hasil Laboratorium.'],
                            ['src' => 'images/panduan-dev/alur/laborat/09-selesai.png', 'caption' => 'TAHAP 5 · Selesai — hasil terkunci, badge KRITIS tetap terlihat (Haemoglobin 9.4 Rendah-KRITIS), siap Cetak Hasil; pembatalan transaksi hanya via eskalasi role.'],
                            ['src' => 'images/panduan-dev/alur/laborat/10-selesai-detail.png', 'caption' => 'TAHAP 5b — detail hasil final: seluruh item + rentang normal + kesimpulan.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/21-hasil-lab-riwayat.png', 'caption' => 'TAHAP 6 · kembali ke EMR — hasil muncul di tab Hasil Penunjang kunjungan (berikut riwayat lab dari UGD/RI); dokter tinggal klik Hasil Laboratorium.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/20-hasil-lab.png', 'caption' => 'TAHAP 6b · EMR — dokter membaca hasil lengkap dengan flag Tinggi/Rendah, langsung dari layar pelayanan. Lingkaran tertutup: order → proses → hasil, tanpa kertas.'],
                        ]])
                    </section>

                    {{-- ====== CABANG — RADIOLOGI ====== --}}
                    <section x-show="section === 'rj-radiologi'" x-cloak>
                        <div class="ds-eyebrow mb-3">Cabang dari EMR</div>
                        <h1 class="ds-display-md mb-4">Order Rad → Modul Radiologi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Radiologi memakai model yang <strong>berbeda dari lab</strong>: bukan input angka
                            per item, melainkan <strong>upload berkas</strong>. Programnya:
                            <strong>Upload Hasil Radiologi</strong>
                            (<span class="ds-code">/transaksi/penunjang/radiologi/upload</span>, role Radiologi /
                            Supervisor Penunjang) — satu layar melayani order RJ, UGD, dan RI.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'EMR', 'judul' => 'Order radiologi', 'sub' => 'atau tambah langsung di modul', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Upload foto', 'sub' => 'rontgen / USG / CT'],
                                ['chip' => null, 'judul' => 'Hasil bacaan', 'sub' => 'dokter radiologi'],
                                ['chip' => 'EMR', 'judul' => 'Terbaca dokter pengirim', 'sub' => 'biaya ke administrasi', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Langkah petugas radiologi</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li>Order dari EMR (atau <strong>Tambah Pemeriksaan</strong> langsung di modul,
                                    mis. permintaan susulan) muncul di list.</li>
                                <li><strong>Upload foto</strong> hasil pemeriksaan (rontgen/USG…).</li>
                                <li>Tulis / generate <strong>hasil bacaan</strong> dokter radiologi —
                                    tersimpan sebagai dokumen bacaan yang rapi untuk dicetak.</li>
                                <li>Dokter pengirim melihat foto + bacaan dari EMR / berkas kunjungan;
                                    biaya radiologi menempel otomatis ke administrasi induk.</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Beda dengan Lab</th>
                                            <th>Laborat</th>
                                            <th>Radiologi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Bentuk hasil', 'Angka per item + flag normal/kritis', 'Foto + dokumen hasil bacaan'],
                                            ['Cara input', 'Manual / tarik alat (Mindray)', 'Upload berkas + tulis bacaan'],
                                            ['Nomor transaksi', 'Satu checkup_no lintas RJ/UGD/RI', 'Per baris order di tabel per jalur'],
                                            ['Integrasi alat', 'Ya (Mindray)', 'Tidak — berkas dari modalitas di-upload'],
                                        ] as [$aspek, $lab, $rad])
                                            <tr>
                                                <td class="ds-td-token">{{ $aspek }}</td>
                                                <td>{{ $lab }}</td>
                                                <td>{{ $rad }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Pembatalan order radiologi dari modul ini ikut menghapus biayanya di kunjungan
                                induk dan tercatat di audit log kunjungan — sama disiplinnya dengan lab.
                            </span>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Proses order end-to-end: EMR → Modul Radiologi → kembali ke EMR</div>
                        @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/16-order-radiologi.png', 'caption' => 'TAHAP 1 · EMR RJ — dokter membuat Order Pemeriksaan Radiologi: pilih item dari katalog 154 pemeriksaan, toggle CITO, isi Diagnosis/Keterangan Klinis (wajib) → Kirim Order.'],
                            ['src' => 'images/panduan-dev/alur/radiologi/01-list.png', 'caption' => 'TAHAP 2 · Modul Radiologi — order muncul di worklist Upload Hasil Radiologi (lintas jalur, badge UGD/RI/RJ; status Antrian → Selesai, tarif terkunci setelah final). Petugas melakukan pemeriksaan lalu meng-upload Foto dari modalitas.'],
                            ['src' => 'images/panduan-dev/alur/radiologi/02-tulis-bacaan.png', 'caption' => 'TAHAP 3 · Hasil Bacaan — dokter radiologi menulis expertise di editor rich-text (dukung tabel ukuran) + memilih penanda tangan → Generate & Simpan menyusun PDF bacaan otomatis.'],
                            ['src' => 'images/panduan-dev/alur/radiologi/03-upload-bacaan.png', 'caption' => 'TAHAP 3b (alternatif) — Upload Hasil Bacaan PDF/JPG (maks 5 MB) bila bacaan dibuat di luar sistem.'],
                            ['src' => 'images/panduan-dev/alur/radiologi/04-foto.png', 'caption' => 'TAHAP 4 — foto & bacaan tersimpan menempel ke order; viewer membuka file modalitas (THORAX PA/AP) langsung dari sistem.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/22-radiologi-riwayat.png', 'caption' => 'TAHAP 5 · kembali ke EMR — di tab Hasil Penunjang kunjungan, order berstatus "Hasil Tersedia" dengan tombol Hasil Bacaan & Foto Radiologi (termasuk riwayat dari UGD/RI).'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/24-hasil-bacaan.png', 'caption' => 'TAHAP 5b · EMR — dokter pengirim membaca expertise resmi (kop Instalasi Radiologi) tanpa meninggalkan layar pelayanan. Lingkaran tertutup: order → foto+bacaan → terbaca dokter.'],
                        ]])
                    </section>

                    {{-- ====== CABANG — E-RESEP ====== --}}
                    <section x-show="section === 'rj-eresep'" x-cloak>
                        <div class="ds-eyebrow mb-3">Cabang dari EMR</div>
                        <h1 class="ds-display-md mb-4">E-Resep → Apotek</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Cabang ketiga dari EMR. Dokter menulis resep di tab <strong>E-Resep</strong> —
                            obat jadi maupun <strong>racikan</strong> (nama racikan + komposisi + aturan pakai).
                            Resep tersimpan di JSON kunjungan dan otomatis muncul di
                            <strong>Antrian Apotek RJ</strong> tanpa perlu kertas.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'Dokter', 'judul' => 'Tulis e-resep', 'sub' => 'obat jadi + racikan', 'chipWarna' => 'sky'],
                                ['chip' => 'taskId 6', 'judul' => 'Antrian apotek', 'sub' => 'tanpa kertas'],
                                ['chip' => 'Apoteker', 'judul' => 'Telaah & siapkan', 'sub' => 'stok berkurang', 'chipWarna' => 'sky'],
                                ['chip' => 'taskId 7', 'judul' => 'Obat diserahkan', 'sub' => 'harga → pos Obat', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Perjalanan satu resep</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li><strong>Dokter</strong> memilih obat dari master (stok apotek terlihat),
                                    menulis signa; racikan disusun dari komponen obat.</li>
                                <li>Resep tampil di <strong>Antrian Apotek RJ</strong> → apoteker menelaah
                                    (telaah resep), menyiapkan, dan menelaah obat (5T) — lihat seksi
                                    <button type="button" class="hover:underline" style="color:var(--primary)"
                                        x-on:click="go('rj-apotek')">Apotek RJ</button>.</li>
                                <li>Saat dilayani, e-resep menjadi <strong>transaksi penjualan farmasi</strong> —
                                    stok berkurang, harga masuk pos <strong>Obat</strong> di administrasi
                                    kunjungan.</li>
                                <li>Resep <strong>kronis / iter</strong> BPJS ditandai statusnya sendiri
                                    (obat kronis bisa dipisah tagihannya dari paket).</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Satu sumber data: EMR, apotek, kasir, cetak lembar resep, dan pengiriman
                                SATUSEHAT (MedicationRequest/Dispense) semuanya membaca node e-resep yang sama
                                di JSON kunjungan — tidak ada penyalinan ulang resep antar layar.
                            </span>
                        </div>
                    </section>