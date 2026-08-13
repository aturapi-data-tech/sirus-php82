                    {{-- ====== RJ 1 — DAFTAR ====== --}}
                    <section x-show="section === 'rj-daftar'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Jalan — Tahap 1</div>
                        <h1 class="ds-display-md mb-4">Daftar RJ (Pendaftaran)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Pintu masuk pasien poli. Petugas pendaftaran (Mr/Tu) mendaftarkan pasien —
                            baik yang datang langsung maupun yang sudah <em>booking</em> lewat Mobile JKN.
                            List default menampilkan <strong>hari ini</strong>, status <strong>Antrian</strong>.
                        </p>

        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'taskId 1–2', 'judul' => 'Pasien datang / Booking JKN', 'sub' => 'admisi memanggil'],
                                ['chip' => null, 'judul' => 'Input pendaftaran', 'sub' => 'pasien · poli · dokter · klaim'],
                                ['chip' => 'BPJS', 'judul' => 'Buat SEP', 'sub' => 'VClaim — rujukan/kontrol'],
                                ['chip' => 'taskId 3', 'judul' => 'Antri poli', 'sub' => 'rj_status = A', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Langkah petugas</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li><strong>Pendaftaran Rawat Jalan</strong> (tombol hijau) → cari pasien dari
                                    Master Pasien (No. RM / NIK / nama). Pasien baru dibuatkan RM dulu di
                                    Master Pasien.</li>
                                <li>Pilih <strong>poli</strong>, <strong>dokter</strong>, <strong>shift</strong>,
                                    dan <strong>jenis klaim</strong> (UMUM / BPJS / kronis / dokel — label BPJS
                                    mengikuti <span class="ds-code">klaim_status</span>).</li>
                                <li>Pasien BPJS: buat <strong>SEP</strong> dari aksi baris (VClaim) — rujukan/kontrol
                                    divalidasi ke BPJS, nomor SEP tersimpan ke kunjungan.</li>
                                <li>Cetak <strong>etiket pasien</strong> / dokumen pendaftaran bila perlu.</li>
                                <li>Pasien dari <strong>Booking RJ</strong>: buka menu Booking → aktivasi menjadi
                                    pendaftaran (waktu booking menjadi stempel daftar poli).</li>
                            </ol>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Aksi per baris pasien</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Aksi</th>
                                            <th>Untuk apa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Edit Pendaftaran', 'Ubah poli/dokter/klaim selama belum dilayani; batal daftar (status F)'],
                                            ['SEP / VClaim', 'Buat, lihat, atau hapus SEP BPJS kunjungan ini'],
                                            ['Diagnosa', 'Isi diagnosa ICD-10 (utk klaim) — bisa juga dari EMR'],
                                            ['iDRG / INA-CBG', 'Kirim data klaim ke grouper casemix'],
                                            ['Kirim SATUSEHAT', 'Kirim Encounter + resource klinis kunjungan'],
                                            ['Berkas BPJS', 'Arsip SEP/klaim/RM/SKDP per pasien'],
                                            ['Task Antrean', 'Stempel manual taskId bila ada yang terlewat'],
                                        ] as [$aksi, $untuk])
                                            <tr>
                                                <td class="ds-td-token">{{ $aksi }}</td>
                                                <td>{{ $untuk }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Yang terjadi di sistem: baris <span class="ds-code">rstxn_rjhdrs</span> dibuat dengan
                                <span class="ds-code">rj_status = 'A'</span> (Antrian) — dan untuk pasien BPJS,
                                stempel antrean <strong>taskId 1</strong> (admisi), <strong>2</strong> (dipanggil),
                                <strong>3</strong> (daftar poli) terkirim ke BPJS.
                            </span>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tampilan program</div>
                        @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
                            ['src' => 'images/panduan-dev/alur/rj-daftar/01-list.png', 'caption' => 'List Daftar Rawat Jalan — filter tanggal/status/klaim/dokter, nomor antrian, progress EMR & E-Resep, tombol SEP · Rekam Medis · SKDP, status kirim SATUSEHAT per resource, dan Batal.'],
                            ['src' => 'images/panduan-dev/alur/rj-daftar/02-form-tambah.png', 'caption' => 'Tambah Data Rawat Jalan — toggle Pasien Baru, cari pasien, cari dokter-poli, dan jenis klaim (UMUM / BPJS / Jasa Raharja / Asuransi Lain / Kronis).'],
                            ['src' => 'images/panduan-dev/alur/rj-daftar/03-cari-pasien.png', 'caption' => 'LOV Cari Pasien — ketik nama / No. RM / NIK / No. BPJS / alamat, hasil menampilkan identitas ringkas untuk verifikasi sebelum dipilih.'],
                            ['src' => 'images/panduan-dev/alur/rj-daftar/04-form-bpjs.png', 'caption' => 'Pasien BPJS — panel Jenis Kunjungan (Rujukan FKTP / Internal / Kontrol / Antar RS + Post Inap), No. Referensi (rujukan/SKDP), tombol Kelola SEP BPJS, plus aksi Etiket · Print Etiket · Scan Wajah.'],
                            ['src' => 'images/panduan-dev/alur/rj-daftar/05-sep.png', 'caption' => 'Kelola Data SEP — form VClaim: spesialis sesuai rujukan, DPJP yang melayani, asal & nomor rujukan, No. Surat Kontrol/SKDP → Simpan SEP.'],
                            ['src' => 'images/panduan-dev/alur/rj-daftar/06-menu-aksi.png', 'caption' => 'Menu aksi ⋯ per pasien — Pendaftaran Ubah · Riwayat Kontrol (Jadwal SKDP RJ/RI) · Diagnosa ICD-10 · Kirim Satu Sehat · Hapus. Tiga tugas rutin Mr berkumpul di sini.'],
                            ['src' => 'images/panduan-dev/alur/rj-daftar/07-satusehat.png', 'caption' => 'Kirim Satu Sehat — checklist resource per kunjungan (Encounter → Condition → Observation → Procedure → Medication Request/Dispense → Chief Complaint → Allergy → Penunjang Lab); tombol Kirim aktif berurutan sesuai prasyarat.'],
                            ['src' => 'images/panduan-dev/alur/rj-daftar/08-jadwal-kontrol.png', 'caption' => 'Riwayat Jadwal Kontrol — seluruh surat kontrol (SKDP) pasien dari kunjungan RJ & RI: kunjungan asal, No. Surat Kontrol, SEP, dan tombol Ubah Tanggal (geser jadwal + update BPJS).'],
                        ]])
                    </section>

                    {{-- ====== RJ 2 — PELAYANAN ====== --}}
                    <section x-show="section === 'rj-pelayanan'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Jalan — Tahap 2</div>
                        <h1 class="ds-display-md mb-4">Pelayanan RJ (EMR Poli)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Worklist <strong>dokter &amp; perawat</strong>. Halaman ini sengaja dipisah dari
                            Daftar RJ: filternya bukan status pendaftaran, melainkan
                            <strong>status pelayanan EMR</strong> (<span class="ds-code">erm_status</span>) —
                            default <strong>Belum Dilayani</strong>. Tiap baris pasien punya tiga tombol:
                            <strong>Rekam Medis</strong> (EMR), <strong>Modul Dokumen</strong>, dan
                            <strong>Administrasi</strong>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'taskId 4', 'judul' => 'Masuk poli', 'sub' => 'dipanggil dari antrian'],
                                ['chip' => 'Perawat', 'judul' => 'Screening & penilaian', 'sub' => 'TTV, nyeri, risiko jatuh, gizi', 'chipWarna' => 'sky'],
                                ['chip' => 'Dokter', 'judul' => 'Anamnesis → Diagnosa', 'sub' => 'pemeriksaan, ICD-10', 'chipWarna' => 'sky'],
                                ['chip' => 'Dokter', 'judul' => 'E-resep & order penunjang', 'sub' => 'lab · rad · rencana pulang/kontrol', 'chipWarna' => 'sky'],
                                ['chip' => 'taskId 5', 'judul' => 'Selesai dilayani', 'sub' => 'erm_status = L', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Isi Rekam Medis (EMR RJ)</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Tab</th>
                                            <th>Diisi oleh</th>
                                            <th>Isi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Screening', 'Perawat', 'Skrining awal: tanda vital, kebangsaan, batuk, dsb.'],
                                            ['Anamnesa / Pengkajian', 'Perawat + Dokter', 'Keluhan utama, riwayat penyakit, alergi (Ya/Tidak + rincian)'],
                                            ['Pemeriksaan', 'Dokter', 'Pemeriksaan fisik + order Lab/Radiologi (Diagnosis/Ket. Klinis wajib)'],
                                            ['Penilaian', 'Perawat', 'Nyeri, risiko jatuh, skrining gizi, risiko bunuh diri'],
                                            ['Diagnosa', 'Dokter', 'ICD-10 (dipakai klaim iDRG & SATUSEHAT)'],
                                            ['Perencanaan', 'Dokter', 'Tindak lanjut: pulang / kontrol (SKDP) / rujuk / transfer UGD-RI'],
                                            ['SKDP', 'Dokter/Perawat', 'Surat kontrol + jadwal kontrol (sinkron BPJS)'],
                                            ['PRB', 'Dokter', 'Program Rujuk Balik pasien kronis'],
                                            ['E-Resep', 'Dokter', 'Resep obat & racikan → masuk antrian Apotek RJ'],
                                            ['Modul Dokumen', 'Semua PPA', 'Consent, edukasi, surat keterangan, dokumen bertanda tangan'],
                                        ] as [$tab, $oleh, $isi])
                                            <tr>
                                                <td class="ds-td-token">{{ $tab }}</td>
                                                <td class="ds-td-meta">{{ $oleh }}</td>
                                                <td>{{ $isi }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Alur singkat di poli</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li>Pasien masuk poli → stempel <strong>taskId 4</strong> (masuk poli).</li>
                                <li>Perawat mengisi screening + penilaian; dokter melengkapi anamnesis,
                                    pemeriksaan, diagnosa, e-resep, dan perencanaan (pulang / kontrol / rujuk).</li>
                                <li>Progress kelengkapan EMR tampil sebagai <strong>persentase</strong> di list —
                                    tombol ⓘ menjelaskan bagian yang belum lengkap.</li>
                                <li>Selesai layanan → status EMR menjadi <strong>Selesai</strong>
                                    (<span class="ds-code">erm_status = 'L'</span>) + stempel
                                    <strong>taskId 5</strong> (keluar poli). Resep otomatis mengantri di Apotek RJ.</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Tindakan klinis otomatis menetes ke rel administrasi: tarif poli, jasa
                                dokter/medis, order lab &amp; radiologi masuk sebagai pos biaya kunjungan —
                                tidak ada input ganda di kasir.
                            </span>
                        </div>

                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Tiga hal yang <em>dikirim</em> dari EMR berlanjut ke program lain dan dikerjakan
                            petugas berbeda:
                            <button type="button" class="hover:underline" style="color:var(--primary)"
                                x-on:click="go('rj-laborat')">Order Lab → Modul Laborat</button> ·
                            <button type="button" class="hover:underline" style="color:var(--primary)"
                                x-on:click="go('rj-radiologi')">Order Rad → Modul Radiologi</button> ·
                            <button type="button" class="hover:underline" style="color:var(--primary)"
                                x-on:click="go('rj-eresep')">E-Resep → Apotek</button>.
                        </p>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tampilan program</div>
                        @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/01-list-aksi.png', 'caption' => 'List Pelayanan RJ (filter erm_status "Belum Dilayani") + menu aksi ⋯: stempel TaskId4/TaskId5/TaskId Antrean, Rekam Medis, Modul Dokumen, Administrasi, Transfer ke UGD. Kolom Tindak Lanjut merekam jejak waktu poli-kasir-apotek per pasien.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/02-screening-rj.png', 'caption' => 'Screening Rawat Jalan — kriteria kegawatan (kesadaran, nafas, nyeri dada, alat bantu, batuk) → keputusan otomatis Aman / Disegerakan / Rujuk IGD + penanda risiko jatuh; terkunci setelah TTD (Buka Kunci khusus role).'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/03-emr-s-o.png', 'caption' => 'Rekam Medis (EMR) pola SOAP — S: Pengkajian perawat (keluhan utama + kode SNOMED SATUSEHAT) · O: Tanda vital & nutrisi. Tombol bawah: Log Aktivitas, Screening, Administrasi, Modul Dokumen, E-Resep.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/04-status-psikologis.png', 'caption' => 'Tab S — Status Psikologis & Status Mental.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/05-screening-batuk.png', 'caption' => 'Tab S — Screening Batuk (gejala & riwayat TB) dengan keterangan opsional per item.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/06-anatomi.png', 'caption' => 'Tab O — Anatomi: kelainan per bagian tubuh (kepala, mata, telinga, …) dengan deskripsi.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/07-nyeri.png', 'caption' => 'Penilaian Nyeri — status Tidak cukup satu klik; riwayat penilaian tercatat per petugas.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/08-nyeri-detail.png', 'caption' => 'Penilaian Nyeri "Ya" — metode disarankan otomatis dari umur pasien (mis. FLACC utk 2 th), lengkap detail pencetus/durasi/lokasi.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/09-risiko-jatuh.png', 'caption' => 'Penilaian Risiko Jatuh + riwayat (metode & skor).'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/10-cssrs.png', 'caption' => 'Skrining Risiko Bunuh Diri C-SSRS — ide (1 bulan) + perilaku (sepanjang hidup), skor keparahan otomatis.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/11-dekubitus.png', 'caption' => 'Penilaian Dekubitus — Skala Braden 6 komponen, interpretasi ≤12 Sangat Tinggi s/d ≥19 Rendah.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/12-gizi.png', 'caption' => 'Penilaian Gizi — BB/TB dengan IMT auto-hitung + Skrining Gizi Awal (skor ≥2 = Berisiko Malnutrisi).'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/13-diagnosa-plan.png', 'caption' => 'A: Diagnosis ICD-10 + free text & Procedure ICD-9-CM · P: Terapi + Dokter Pemeriksa · R: panel riwayat kunjungan dengan Copy Resep / Resume Medis.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/14-order-lab.png', 'caption' => 'Order Pemeriksaan Laboratorium — katalog item + tarif, toggle CITO, Diagnosis/Keterangan Klinis wajib; item terpilih tampil di keranjang kanan.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/15-order-lab-luar.png', 'caption' => 'Order Laboratorium Luar (rujukan) — nama pemeriksaan bebas + catatan klinis.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/16-order-radiologi.png', 'caption' => 'Order Pemeriksaan Radiologi — katalog 154 item + CITO + keterangan klinis wajib.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/17-tab-penunjang.png', 'caption' => 'Tab O — Pelayanan Penunjang: tombol Order Laboratorium / Lab Luar (dan Radiologi) + tabel status order kunjungan ini.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/18-upload-penunjang.png', 'caption' => 'Tab O — Upload Penunjang: unggah hasil PDF/JPG dari luar (EKG, hasil bawa pasien).'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/19-hasil-penunjang.png', 'caption' => 'Tab O — Hasil Penunjang: rekap lab/radiologi/upload pasien lintas kunjungan dengan counter Terdaftar/Proses/Selesai.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/20-hasil-lab.png', 'caption' => 'Hasil Laboratorium — nilai + rentang normal + flag Tinggi/Rendah otomatis; siap Cetak Hasil.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/21-hasil-lab-riwayat.png', 'caption' => 'Riwayat pemeriksaan lab pasien (termasuk dari UGD/RI) — dokter poli langsung membuka hasil lama tanpa pindah program.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/22-radiologi-riwayat.png', 'caption' => 'Riwayat radiologi lintas jalur (RI: Proses, UGD: Hasil Tersedia) dengan tombol Hasil Bacaan & Foto Radiologi.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/23-foto-radiologi.png', 'caption' => 'Foto Radiologi — file DICOM-print (PDF) dari modalitas dibuka langsung di EMR.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/24-hasil-bacaan.png', 'caption' => 'Hasil Bacaan Radiologi — expertise dokter radiologi (kop Instalasi Radiologi) dibaca dari EMR.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/25-eresep.png', 'caption' => 'E-Resep RJ — tab Non Racikan: cari obat, jumlah, signa; panel kanan riwayat resep + Copy Resep; toggle Status PRB / Iter untuk BPJS.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/26-eresep-racikan.png', 'caption' => 'Racikan di P-Plan — komposisi R1/R2 per komponen (dosis pecahan 1/5 tab dll.) + jumlah racikan & signa, terekam utuh di riwayat.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/27-eresep-riwayat.png', 'caption' => 'Riwayat resep lintas poli pasien (POLI PARU, POLI JANTUNG…) — dokter melihat terapi berjalan sebelum menulis resep baru.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/28-resume-1.png', 'caption' => 'Resume Medis (3 halaman) — hal. 1: Assesment Awal RJ berisi hasil screening + keputusan, perawat, dan tab Modul Dokumen / Hasil Penunjang.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/29-resume-2.png', 'caption' => 'Resume — anamnesa, seluruh penilaian (nyeri s/d gizi), tanda vital & nutrisi terangkum otomatis.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/30-resume-3.png', 'caption' => 'Resume — keadaan umum, diagnosis, tindak lanjut, dan terapi lengkap.'],
                            ['src' => 'images/panduan-dev/alur/rj-pelayanan/31-resume-ttd.png', 'caption' => 'Resume — ditutup TTD perawat/terapis & dokter pemeriksa, siap Cetak PDF.'],
                        ]])
                    </section>

                    {{-- ====== RJ 3 — APOTEK ====== --}}
                    <section x-show="section === 'rj-apotek'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Jalan — Tahap 3</div>
                        <h1 class="ds-display-md mb-4">Apotek RJ</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Antrian farmasi membaca <strong>e-resep</strong> yang ditulis dokter di EMR.
                            Filter default: tanggal hari ini + <strong>belum serah obat</strong>
                            (taskId 7 kosong) — jadi layar apoteker selalu berisi pekerjaan yang tersisa.
                            List menyegarkan diri otomatis (poll), filter tersimpan per tab.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'taskId 6', 'judul' => 'Resep masuk', 'sub' => 'antrian apotek'],
                                ['chip' => 'TTD', 'judul' => 'Telaah resep', 'sub' => 'administratif & farmasetis', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Siapkan obat', 'sub' => 'harga masuk pos Obat'],
                                ['chip' => 'TTD', 'judul' => 'Telaah obat', 'sub' => 'verifikasi 5T', 'chipWarna' => 'sky'],
                                ['chip' => 'taskId 7', 'judul' => 'Serah obat', 'sub' => 'hilang dari antrian', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Langkah apoteker</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li>Resep masuk antrian → tandai <strong>taskId 6</strong> (pasien masuk apotek /
                                    resep mulai dikerjakan).</li>
                                <li><strong>Telaah Resep</strong> — kelengkapan administratif &amp; farmasetis +
                                    TTD apoteker penelaah.</li>
                                <li>Obat disiapkan; harga obat masuk pos <strong>Obat</strong> di administrasi
                                    kunjungan (e-resep menjadi transaksi penjualan farmasi).</li>
                                <li><strong>Telaah Obat</strong> — verifikasi 5T sebelum penyerahan + TTD.</li>
                                <li>Obat diserahkan ke pasien → tandai <strong>taskId 7</strong> (obat diserahkan).
                                    Kartu pasien hilang dari filter default karena pekerjaan selesai.</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Stempel taskId idempoten — diset hanya bila kosong dan dikirim ulang hanya bila
                                BPJS belum menjawab 200/208, jadi klik dobel tidak menggeser waktu tunggu.
                                Nomor antrean apotek juga dilaporkan terpisah ke BPJS
                                (<span class="ds-code">tambahAntrianApotek</span>).
                            </span>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tampilan program</div>
                        @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
                            ['src' => 'images/panduan-dev/alur/rj-apotek/01-antrian.png', 'caption' => 'Antrian Apotek (tab Rawat Jalan / UGD / Rawat Inap) — filter "Belum Diserahkan" + auto-refresh 20 detik; per pasien: jejak Waktu Apotek (keluar poli, kasir, masuk/keluar apotek), tombol TaskId6/TaskId7, Telaah Resep & Obat, Cetak E-Resep, Administrasi, dan Cek Saldo Kas.'],
                            ['src' => 'images/panduan-dev/alur/rj-apotek/02-telaah.png', 'caption' => 'Telaah Resep & Obat satu layar — kiri telaah resep (kejelasan tulisan, tepat obat/dosis/rute/waktu, duplikasi, alergi, interaksi, BB anak, kontraindikasi), kanan telaah obat (sesuai resep: obat, jumlah & dosis, rute, waktu) + ringkasan ✓/✗ dan TTD-E apoteker.'],
                            ['src' => 'images/panduan-dev/alur/rj-apotek/03-cetak-eresep.png', 'caption' => 'Cetak E-Resep (PDF) — lembar resep resmi berisi daftar obat + hasil pengkajian resep & obat, ditandatangani dokter, apoteker penelaah, dan pasien.'],
                            ['src' => 'images/panduan-dev/alur/rj-apotek/04-administrasi-obat.png', 'caption' => 'Administrasi — tab Obat: harga obat terlayani masuk pos Obat (badge KRONIS per item), cetak etiket per obat, Status Pengambilan Obat; terkunci setelah pasien pulang.'],
                            ['src' => 'images/panduan-dev/alur/rj-apotek/05-rincian-pos.png', 'caption' => 'Rincian tagihan — panel pos biaya (RS Admin, Admin OB, Uang Periksa, Jasa Karyawan/Dokter/Medis, Obat, Lab, Radiologi, Kamar Operasi, Lain-Lain) dengan Total Tagihan berjalan.'],
                            ['src' => 'images/panduan-dev/alur/rj-apotek/06-kwitansi-obat.png', 'caption' => 'Kwitansi Obat — bukti biaya obat kunjungan (dipakai saat pasien membayar obat terpisah).'],
                            ['src' => 'images/panduan-dev/alur/rj-apotek/07-kwitansi-rj.png', 'caption' => 'Kwitansi Rawat Jalan — seluruh pos kunjungan (admin RS, uang periksa, jasa medis, jasa karyawan, obat) + terbilang; identitas & No. SEP/Referensi tercantum.'],
                        ]])
                    </section>

                    {{-- ====== RJ 4 — KASIR ====== --}}
                    <section x-show="section === 'rj-kasir'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Jalan — Tahap 4</div>
                        <h1 class="ds-display-md mb-4">Kasir RJ</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Ujung alur: petugas Tu membuka <strong>Antrian Kasir RJ</strong> (default: hari ini,
                            status Antrian) lalu membuka <strong>Administrasi</strong> pasien — layar yang sama
                            yang bisa dibuka dari Pelayanan RJ. Semua pos biaya sudah terisi otomatis dari
                            tindakan; kasir tinggal memeriksa, menambah yang manual, dan menerima pembayaran.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'otomatis', 'judul' => 'Pos biaya terkumpul', 'sub' => 'poli · jasa · obat · lab · rad'],
                                ['chip' => null, 'judul' => 'Periksa ringkasan', 'sub' => 'Total − Diskon − Sudah Bayar = Sisa'],
                                ['chip' => 'kasir', 'judul' => 'Terima pembayaran', 'sub' => 'akun kas + bayar → kembalian', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Cetak kwitansi', 'sub' => 'jurnal kas terbentuk'],
                                ['chip' => 'rj_status L', 'judul' => 'Kunjungan ditutup', 'sub' => 'administrasi terkunci', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Pos biaya di Administrasi RJ</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Pos</th>
                                            <th>Sumber</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Admin RS & Admin RJ + Tarif Poli', 'Otomatis saat pendaftaran'],
                                            ['Jasa Medis / Jasa Dokter / Jasa Karyawan', 'Tindakan di EMR; bisa ditambah manual (LOV tarif)'],
                                            ['Obat', 'E-resep yang dilayani Apotek RJ'],
                                            ['Laboratorium', 'Order lab dari EMR (biaya menempel saat hasil diproses)'],
                                            ['Radiologi', 'Order radiologi dari EMR'],
                                            ['Kamar Operasi', 'Transfer biaya dari modul OK (bila ada tindakan)'],
                                            ['Lain-lain', 'Input manual (materai, administrasi khusus, dsb.)'],
                                        ] as [$pos, $sumber])
                                            <tr>
                                                <td class="ds-td-token">{{ $pos }}</td>
                                                <td>{{ $sumber }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Langkah kasir</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li>Periksa ringkasan: <strong>Total</strong> − <strong>Diskon</strong> −
                                    <strong>Sudah Bayar</strong> = <strong>Sisa</strong>. Pasien BPJS murni
                                    biasanya sisa 0 (ditanggung penjamin).</li>
                                <li>Pilih <strong>akun kas</strong> (tunai/transfer/EDC) dan masukkan jumlah
                                    <strong>bayar</strong> — kembalian dihitung otomatis.</li>
                                <li>Simpan pembayaran → cetak <strong>kwitansi</strong>.</li>
                                <li>Kunjungan ditutup: <span class="ds-code">rj_status = 'L'</span> (Selesai).
                                    Pembayaran otomatis membentuk jurnal kas di modul keuangan.</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Setelah lunas, layar administrasi terkunci untuk perubahan biaya — pembukaan
                                kembali hanya oleh role tertentu (Admin/Tu). Rincian model datanya ada di
                                <a href="{{ route('panduan-dev.koding-administrasi') }}" wire:navigate
                                    class="hover:underline" style="color:var(--primary)">Tutorial Koding
                                    Administrasi</a>.
                            </span>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tampilan program</div>
                        @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
                            ['src' => 'images/panduan-dev/alur/rj-kasir/01-antrian-kasir.png', 'caption' => 'Antrian Kasir Rawat Jalan — filter status Antrian + auto-refresh; kolom Waktu Kasir merekam keluar poli / masuk-keluar apotek per pasien; aksi: Administrasi & Transfer ke UGD.'],
                            ['src' => 'images/panduan-dev/alur/rj-kasir/02-jasa-karyawan.png', 'caption' => 'Administrasi — tab Jasa Karyawan: tambah tarif via LOV (kode + harga), Total Tagihan di header ter-update langsung.'],
                            ['src' => 'images/panduan-dev/alur/rj-kasir/03-jasa-dokter.png', 'caption' => 'Tab Jasa Dokter — pilih dokter (bisa diubah) + jasa/tindakannya via LOV (tarif BPJS/UMUM bisa berbeda).'],
                            ['src' => 'images/panduan-dev/alur/rj-kasir/04-jasa-medis.png', 'caption' => 'Tab Jasa Medis — tarif tindakan medis ditambahkan per baris dengan LOV.'],
                            ['src' => 'images/panduan-dev/alur/rj-kasir/05-obat.png', 'caption' => 'Tab Obat — selain otomatis dari e-resep, obat bisa ditambah manual (LOV produk menampilkan kandungan & harga).'],
                            ['src' => 'images/panduan-dev/alur/rj-kasir/06-lain-lain.png', 'caption' => 'Tab Lain-Lain — biaya non-standar (disinfektan mobil, ECG monitor, transport jenazah, …) via LOV.'],
                            ['src' => 'images/panduan-dev/alur/rj-kasir/07-kasir-bayar.png', 'caption' => 'Tab Kasir — Panduan Kasir RJ + Subtotal − Diskon = Total; input Bayar menghitung Kembalian; pilih Akun Kas → Post Transaksi (LUNAS + jurnal kas). Batal Transaksi (status F) hanya bila belum ada transaksi layanan — Task ID 99 BPJS terpisah.'],
                            ['src' => 'images/panduan-dev/alur/rj-kasir/08-transfer-ugd.png', 'caption' => 'Transfer ke Gawat Darurat — seluruh Total Administrasi RJ dipindahkan menjadi tagihan UGD (rincian per pos ditampilkan); pilih cara masuk, jenis klaim, dan dokter UGD → Konfirmasi. Pasien membayar sekali di ujung UGD.'],
                        ]])
                    </section>