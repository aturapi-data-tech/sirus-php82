                    {{-- ====== SATUSEHAT 0 — PENGANTAR ====== --}}
                    <section x-show="section === 'ss-pengantar'" x-cloak>
                        <div class="ds-eyebrow mb-3">SATUSEHAT — Pengantar</div>
                        <h1 class="ds-display-md mb-4">Kirim SATUSEHAT: Siapa, Kapan, Di Mana</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Setiap kunjungan pasien (Rawat Jalan, UGD, Rawat Inap) wajib dilaporkan ke platform
                            <strong>SATUSEHAT</strong> Kemenkes. Yang dinilai bukan sekadar "sudah pernah kirim", tetapi
                            <strong>kunjungan yang tuntas</strong>: kunjungan ditutup (finished), diagnosis terisi, dan resume
                            medis terbentuk. Persentase pengiriman dinilai berkala dan berpengaruh ke akreditasi.
                        </p>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Kuncinya sederhana: <strong>SIRUS hanya mengirim apa yang sudah ditulis di EMR.</strong>
                            Kartu SATUSEHAT tidak bisa diisi manual — kalau isian EMR kosong, kartu menolak dengan pesan yang
                            menyebut apa yang kurang. Jadi pekerjaan terbesarnya ada di <strong>kelengkapan EMR</strong>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'Dokter · Perawat · Apoteker', 'judul' => '1. Isi EMR', 'sub' => 'anamnesa, pemeriksaan, diagnosa, resep, perencanaan', 'chipWarna' => 'sky'],
                                ['chip' => 'Dokter · Perawat · Mr', 'judul' => '2. Coding SATUSEHAT', 'sub' => 'pilih kode SNOMED/KFA untuk teks EMR', 'chipWarna' => 'amber'],
                                ['chip' => 'Mr · Admin', 'judul' => '3. Kirim Satu Sehat', 'sub' => 'tombol Kirim Semua (berurutan)'],
                                ['chip' => 'Mr · Admin', 'judul' => '4. Finish Encounter', 'sub' => 'kunjungan ditutup + cara keluar'],
                                ['chip' => 'Mr · Admin', 'judul' => '5. Resume Medis', 'sub' => 'paling akhir', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Siapa mengerjakan apa</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Peran</th><th>Tugas untuk SATUSEHAT</th><th>Hak akses di SIRUS</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Perawat</td><td class="ds-body-sm">Tanda vital, skala nyeri &amp; kesadaran, risiko jatuh &amp; gizi, Pengkajian Perawat (UGD: jam datang, sarana transportasi, surat rujukan, tingkat triase; TTD Screening), observasi lanjutan (RI)</td><td class="ds-body-sm">EMR + layar Coding SATUSEHAT</td></tr>
                                        <tr><td class="ds-td-strong">Dokter</td><td class="ds-body-sm">Anamnesa (keluhan utama, riwayat, alergi), diagnosa ICD-10, tindakan ICD-9, E-Resep, Perencanaan (waktu pemeriksaan, tindak lanjut, kondisi saat keluar, prognosis, discharge planning), CPPT (RI)</td><td class="ds-body-sm">EMR + layar Coding SATUSEHAT</td></tr>
                                        <tr><td class="ds-td-strong">Apoteker</td><td class="ds-body-sm">Telaah resep (15 butir) dan penyerahan obat — jam penyerahan obat menjadi jam selesai kunjungan RJ/UGD</td><td class="ds-body-sm">Antrian Apotek</td></tr>
                                        <tr><td class="ds-td-strong">Laborat / Radiologi</td><td class="ds-body-sm">Hasil pemeriksaan sampai selesai; radiologi: bacaan diunggah</td><td class="ds-body-sm">Modul Laborat / Radiologi</td></tr>
                                        <tr><td class="ds-td-strong">Kasir</td><td class="ds-body-sm">Transfer pasien RJ → UGD dan UGD → RI lewat tombol Transfer (inilah yang menautkan kunjungan di SATUSEHAT)</td><td class="ds-body-sm">Kasir RJ / UGD</td></tr>
                                        <tr><td class="ds-td-strong">Rekam Medis (Mr)</td><td class="ds-body-sm">Melengkapi Coding SATUSEHAT, menekan Kirim Satu Sehat, Finish Encounter, Resume Medis, menindaklanjuti kartu yang gagal</td><td class="ds-body-sm">Coding SATUSEHAT + <strong>Kirim Satu Sehat</strong> (hanya Mr &amp; Admin)</td></tr>
                                        <tr><td class="ds-td-strong">Farmasi (master)</td><td class="ds-body-sm">Memetakan kode <strong>KFA</strong> di Master Obat — obat tanpa KFA tidak ikut terkirim</td><td class="ds-body-sm">Master Obat</td></tr>
                                        <tr><td class="ds-td-strong">IT / Admin</td><td class="ds-body-sm">IHS dokter, Location poli &amp; kamar, IHS pasien di Master Pasien; memantau di Monitoring SATUSEHAT</td><td class="ds-body-sm">Master + Manajemen</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Di mana tombolnya</div>
                        <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Daftar RJ / UGD / RI → menu titik-3 baris pasien</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><strong>Coding SATUSEHAT</strong> — "Kode SNOMED sesudah pelayanan". Dokter, Perawat, Mr, Manager Medis, Manager Umum, Admin.</li>
                                    <li><strong>Kirim Satu Sehat</strong> — modal berisi kartu per data. Hanya <strong>Mr &amp; Admin</strong>.</li>
                                    <li>Halaman: <span class="ds-code">/rj/daftar</span>, <span class="ds-code">/ugd/daftar</span>, <span class="ds-code">/ri/daftar</span>.</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Chip status di baris pasien</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Kotak kecil "Satu Sehat" di tiap baris: <strong>abu-abu = belum dikirim</strong>, <strong>hijau = sudah</strong>, plus hitungan terkirim/total.</li>
                                    <li>Klik chip mana pun = membuka modal Kirim Satu Sehat.</li>
                                    <li>Rekap seluruh RS: <span class="ds-code">/manajemen/rs/satu-sehat/monitoring-satu-sehat</span>.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Layar Coding SATUSEHAT</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <p class="ds-body-sm mb-3">
                                EMR ditulis dengan istilah sehari-hari (mis. "pusing berputar"). SATUSEHAT meminta <strong>kode baku</strong>
                                (SNOMED untuk keluhan/riwayat/alergi, KFA untuk obat). Layar Coding memasangkan keduanya — teks EMR
                                tetap seperti yang ditulis dokter/perawat.
                            </p>
                            <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                <li><strong>Anamnesa</strong> — tab Keluhan (keluhan utama + keluhan penyerta), Riwayat Penyakit, Riwayat Keluarga, Riwayat Pengobatan (obat ber-KFA), Alergi, Status Psikologis. UGD punya tab tambahan <strong>Kedatangan</strong>.</li>
                                <li><strong>Tanda Vital &amp; Nutrisi</strong> (RJ/UGD) / <strong>Pemeriksaan Fisik (Pengkajian Awal)</strong> (RI) — periksa nilai yang akan dikirim.</li>
                                <li><strong>Diagnosis &amp; Prosedur</strong>, <strong>Perencanaan</strong>.</li>
                                <li>Bingkai <strong>"Yang akan dikirim ke SATUSEHAT"</strong> menunjukkan hasil akhirnya sebelum Mr menekan kirim.</li>
                                <li>Cara memilih kode: ketik kata kunci (Indonesia/Inggris), pilih dari hasil pencarian. Jangan memilih kode "riwayat …" untuk keluhan sekarang — SNOMED membedakan "hipertensi" dan "riwayat hipertensi".</li>
                            </ul>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Cara kerja tombol Kirim Semua</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-2" style="list-style:decimal; padding-left:1.4em">
                                <li>Buka modal Kirim Satu Sehat → tekan <strong>Kirim Semua</strong> → konfirmasi.</li>
                                <li>Kartu dijalankan <strong>berurutan</strong>, satu per satu, mulai dari Encounter (kunjungan) — semua data lain menempel ke Encounter, jadi Encounter wajib pertama.</li>
                                <li>Kartu yang datanya belum lengkap <strong>ditolak dan dilewati</strong>, rantai tetap lanjut. Pesannya muncul sebagai notifikasi.</li>
                                <li>Selama berjalan, layar dikunci dan muncul tulisan "Mengirim … · sisa n langkah". Tombol <strong>Hentikan</strong> menghentikan langkah berikutnya.</li>
                                <li>Urutan penutup: <strong>Finish Encounter → Resume Medis</strong> (RI: juga Finish Episode).</li>
                                <li>Kartu yang sudah terkirim berwarna hijau "Terkirim" dan <strong>tidak dikirim dua kali</strong> — aman menekan Kirim Semua lagi setelah melengkapi EMR.</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px; border-left:3px solid #f59e0b">
                            <div class="ds-title-sm mb-1">Penting</div>
                            <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                <li><strong>Kiriman yang sudah berangkat tidak bisa dibatalkan</strong> dari SIRUS. Periksa EMR dulu, baru kirim.</li>
                                <li>Mengubah EMR <strong>sesudah</strong> kartu terkirim tidak otomatis memperbarui data di SATUSEHAT.</li>
                                <li>Semua jam diambil dari isian EMR, <strong>bukan jam saat tombol ditekan</strong>. Kalau jamnya kosong, kartu menolak — isi jamnya di EMR, jangan dibiarkan.</li>
                                <li>Lakukan pengiriman <strong>setelah pelayanan selesai</strong> (obat sudah diserahkan / pasien pulang). Encounter boleh dikirim lebih awal, tetapi Finish hanya bisa setelah jam selesai tercatat.</li>
                            </ul>
                        </div>
                    </section>

                    {{-- ====== SATUSEHAT 1 — RAWAT JALAN ====== --}}
                    <section x-show="section === 'ss-rj'" x-cloak>
                        <div class="ds-eyebrow mb-3">SATUSEHAT — Rawat Jalan</div>
                        <h1 class="ds-display-md mb-4">Kirim SATUSEHAT Rawat Jalan</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Kunjungan poli. Status kunjungan di SATUSEHAT: <strong>datang → dilayani → selesai</strong>.
                            Tabel di bawah = urutan kartu saat Kirim Semua, beserta <strong>isian EMR yang harus ada</strong>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'arrived', 'judul' => 'Datang', 'sub' => 'tanggal kunjungan (pendaftaran)', 'chipWarna' => 'sky'],
                                ['chip' => 'in-progress', 'judul' => 'Dilayani', 'sub' => 'pasien di poli'],
                                ['chip' => 'finished', 'judul' => 'Selesai', 'sub' => 'obat diserahkan (task 7) / keluar poli (task 5)', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Prasyarat sebelum menekan Kirim (sekali periksa)</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-sm space-y-1.5" style="list-style:none; padding-left:0">
                                <li>☐ Pasien punya <strong>IHS / UUID</strong> di Master Pasien (tombol "Update patientUuid" — butuh NIK 16 digit).</li>
                                <li>☐ Dokter punya <strong>IHS</strong> di Master Dokter, poli punya <strong>Location</strong> di Master Poli.</li>
                                <li>☐ Diagnosa ICD-10 sudah diisi (wajib untuk menutup kunjungan).</li>
                                <li>☐ <strong>Kondisi Saat Keluar</strong> dan <strong>Tindak Lanjut</strong> sudah dipilih di Perencanaan EMR.</li>
                                <li>☐ Obat sudah diserahkan apotek (task 7) — atau pasien sudah keluar poli (task 5) bila tanpa obat.</li>
                                <li>☐ Coding SATUSEHAT sudah dilengkapi (minimal keluhan utama &amp; alergi).</li>
                            </ul>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Urutan kartu &amp; sumber datanya</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Kartu</th><th>Diisi di mana</th><th>Oleh</th><th>Kalau kosong</th></tr></thead>
                                    <tbody>
                                        @foreach ([
                                            ['Encounter (kunjungan)', 'Pendaftaran RJ + IHS pasien/dokter/poli', 'Mr / IT', 'Ditolak — wajib pertama, kartu lain menunggu'],
                                            ['Diagnosa (Condition)', 'EMR → Diagnosa ICD-10', 'Dokter / Mr', 'Ditolak; kunjungan tak bisa ditutup'],
                                            ['Tanda Vital', 'EMR → Pemeriksaan (TD, nadi, napas, suhu, SpO₂, BB/TB)', 'Perawat', 'Dilewati'],
                                            ['Tindakan (Procedure)', 'EMR → Diagnosa → prosedur ICD-9', 'Dokter / Mr', 'Dilewati'],
                                            ['Resep (MedicationRequest)', 'E-Resep dokter; obat harus ber-KFA di Master Obat', 'Dokter / Farmasi', 'Obat tanpa KFA disebut namanya, dilewati'],
                                            ['Keluhan Utama', 'Teks: EMR Anamnesa · Kode: Coding → Anamnesa → Keluhan', 'Dokter · Mr', 'Ditolak bila kode belum dipilih'],
                                            ['Keluhan Penyerta', 'Coding → Keluhan (daftar)', 'Dokter / Mr', 'Dilewati'],
                                            ['Riwayat Penyakit', 'Coding → Riwayat Penyakit', 'Dokter / Mr', 'Dilewati'],
                                            ['Riwayat Keluarga', 'Coding → Riwayat Keluarga', 'Dokter / Mr', 'Dilewati'],
                                            ['Riwayat Pengobatan', 'Coding → Riwayat Pengobatan (obat ber-KFA)', 'Dokter / Mr', 'Dilewati'],
                                            ['Riwayat Perjalanan Penyakit', 'EMR Anamnesa → riwayat penyakit sekarang (teks)', 'Dokter', 'Dilewati'],
                                            ['Alergi', 'EMR Anamnesa alergi ("Tidak ada alergi" juga dikirim) + Coding → Alergi', 'Dokter / Perawat · Mr', 'Ditolak bila belum diisi'],
                                            ['Obat Diserahkan (Dispense)', 'Otomatis dari resep, setelah kartu Resep terkirim', '—', 'Menunggu kartu Resep'],
                                            ['Laborat', 'Modul Laborat — hasil selesai; item ber-LOINC di master', 'Laborat', 'Item tanpa LOINC dilewati'],
                                            ['Radiologi', 'Order + bacaan radiologi', 'Radiologi', 'Dilewati bila belum ada bacaan'],
                                            ['Impresi Klinik', 'Otomatis dari daftar diagnosa', '—', 'Menunggu diagnosa'],
                                            ['Penilaian', 'EMR → Penilaian risiko jatuh &amp; gizi', 'Perawat', 'Dilewati'],
                                            ['Nyeri &amp; Kesadaran', 'EMR → skala nyeri + tingkat kesadaran', 'Perawat', 'Dilewati'],
                                            ['Status Psikologis', 'EMR Anamnesa → status psikologis', 'Perawat / Dokter', 'Dilewati'],
                                            ['Telaah Resep', 'Antrian Apotek → telaah resep 15 butir', 'Apoteker', 'Dilewati'],
                                            ['Prognosis', 'EMR → Perencanaan → prognosis', 'Dokter', 'Dilewati'],
                                            ['Rencana Tindak Lanjut', 'Jadwal kontrol / SKDP (tanggal kontrol)', 'Dokter / Mr', 'Opsional — dilewati'],
                                            ['Kondisi Saat Keluar', 'EMR → Perencanaan → Kondisi Saat Keluar', 'Dokter', '<strong>WAJIB</strong> — ditolak'],
                                            ['Finish Encounter', 'Jam selesai (task 7 → task 5) + diagnosa terkirim + Tindak Lanjut', 'Mr', 'Ditolak, kunjungan menggantung'],
                                            ['Resume Medis', 'Otomatis merangkum kartu yang sudah terkirim', 'Mr', 'Kirim terakhir, setelah Finish'],
                                        ] as $baris)
                                            <tr>
                                                <td class="ds-td-strong">{!! $baris[0] !!}</td>
                                                <td class="ds-body-sm">{!! $baris[1] !!}</td>
                                                <td class="ds-body-sm">{{ $baris[2] }}</td>
                                                <td class="ds-body-sm">{!! $baris[3] !!}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Langkah petugas Rekam Medis</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-2" style="list-style:decimal; padding-left:1.4em">
                                <li>Buka <span class="ds-code">/rj/daftar</span>, cari pasien (filter tanggal/poli).</li>
                                <li>Menu titik-3 → <strong>Coding SATUSEHAT</strong>: lengkapi kode keluhan utama, alergi, riwayat; periksa bingkai "Yang akan dikirim".</li>
                                <li>Menu titik-3 → <strong>Kirim Satu Sehat</strong> → <strong>Kirim Semua</strong>.</li>
                                <li>Baca notifikasi kuning/merah. Lengkapi isian EMR yang disebut, lalu tekan Kirim Semua lagi — yang sudah hijau tidak dikirim ulang.</li>
                                <li>Pastikan kartu <strong>Finish Encounter</strong> dan <strong>Resume Medis</strong> hijau. Chip di baris pasien harus penuh.</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <div class="ds-title-sm mb-1">Cara keluar (otomatis dari Tindak Lanjut)</div>
                            <div class="ds-body-sm">
                                Kontrol / Perawatan Selesai / PRB → <em>pulang</em> · Rujuk → <em>dirujuk ke fasyankes lain</em> ·
                                MRS → <em>dirawat inap</em> · Lain-lain → <em>lain-lain</em>. Bila pasien <strong>ditransfer ke UGD lewat kasir</strong>,
                                cara keluar otomatis menjadi <strong>"Dipindahkan ke IGD (UGD #…)"</strong> — lihat
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('ss-transfer')">Pasien Pindahan</button>.
                                Tindak Lanjut kosong → kunjungan tetap ditutup tetapi <strong>tanpa cara keluar</strong> (notifikasi kuning).
                            </div>
                        </div>
                    </section>

                    {{-- ====== SATUSEHAT 2 — UGD ====== --}}
                    <section x-show="section === 'ss-ugd'" x-cloak>
                        <div class="ds-eyebrow mb-3">SATUSEHAT — UGD</div>
                        <h1 class="ds-display-md mb-4">Kirim SATUSEHAT UGD</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Sebagian besar kartu sama dengan Rawat Jalan. Yang <strong>khas UGD</strong>: status <strong>triase</strong>,
                            data <strong>kedatangan</strong>, <strong>pemberian obat</strong> di IGD, dan <strong>rencana pemulangan</strong>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'arrived', 'judul' => 'Datang', 'sub' => 'tanggal masuk UGD', 'chipWarna' => 'sky'],
                                ['chip' => 'triaged', 'judul' => 'Ditriase', 'sub' => 'jam TTD Screening (cadangan: jam datang)', 'chipWarna' => 'amber'],
                                ['chip' => 'in-progress', 'judul' => 'Diperiksa dokter', 'sub' => 'Waktu Pemeriksaan di Perencanaan'],
                                ['chip' => 'finished', 'judul' => 'Selesai', 'sub' => 'obat diserahkan / Selesai Pemeriksaan', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Isian EMR yang khas UGD</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Untuk</th><th>Isi di</th><th>Oleh</th><th>Akibat kalau kosong</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Status triase (triaged)</td><td class="ds-body-sm"><strong>TTD Screening</strong> (Screening UGD) + <strong>tingkat triase</strong> P1–P3 (Pengkajian Perawat, atau saran Screening). Cadangan jam: <strong>Jam Datang</strong> Pengkajian Perawat</td><td class="ds-body-sm">Perawat</td><td class="ds-body-sm">Kunjungan terkirim <strong>tanpa status triase</strong> — notifikasi kuning, kartu bertuliskan "tanpa triaged". <em>Tidak bisa ditambahkan belakangan</em>, jadi lengkapi sebelum kirim.</td></tr>
                                        <tr><td class="ds-td-strong">Jam mulai diperiksa</td><td class="ds-body-sm">Perencanaan → Petugas Medis → <strong>Waktu Pemeriksaan</strong> (terisi juga saat E-Resep disimpan)</td><td class="ds-body-sm">Dokter</td><td class="ds-body-sm">Dipakai jam triase/datang</td></tr>
                                        <tr><td class="ds-td-strong">Kedatangan</td><td class="ds-body-sm">Pengkajian Perawat → sarana transportasi, surat pengantar rujukan, tingkat triase · kode di Coding → Kedatangan</td><td class="ds-body-sm">Perawat · Mr</td><td class="ds-body-sm">Bagian yang kosong dilewati. Triase <strong>P0</strong> (meninggal saat tiba) belum ikut terkirim.</td></tr>
                                        <tr><td class="ds-td-strong">Pemberian Obat</td><td class="ds-body-sm">Obat &amp; Cairan UGD (obat yang diberikan di IGD)</td><td class="ds-body-sm">Perawat</td><td class="ds-body-sm">Obat tanpa KFA dilewati &amp; disebut</td></tr>
                                        <tr><td class="ds-td-strong">Rencana Pemulangan</td><td class="ds-body-sm">Perencanaan → <strong>Discharge Planning</strong>: pelayanan berkelanjutan (perawatan luka, fisioterapi, homecare, hemodialisa, rujuk balik FKTP, …) dan alat bantu (kursi roda, tongkat, oksigen, kateter, NGT, …) — pilih, isi, tekan <strong>+ Tambah</strong></td><td class="ds-body-sm">Dokter / Perawat</td><td class="ds-body-sm">Opsional — "Tidak Ada" dilewati</td></tr>
                                        <tr><td class="ds-td-strong">Jam selesai</td><td class="ds-body-sm">Penyerahan obat (task 7) → cadangan <strong>Selesai Pemeriksaan</strong> di Perencanaan</td><td class="ds-body-sm">Apoteker / Dokter</td><td class="ds-body-sm">Finish ditolak</td></tr>
                                        <tr><td class="ds-td-strong">Cara keluar</td><td class="ds-body-sm">Tindak Lanjut: MRS, Kontrol, Rujuk, Perawatan Selesai, Meninggal, Lain-lain</td><td class="ds-body-sm">Dokter</td><td class="ds-body-sm">Ditutup tanpa cara keluar (kuning)</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Urutan kartu UGD</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <p class="ds-body-sm">
                                Encounter (+ triase otomatis) → <strong>Kedatangan</strong> → Diagnosa → Tanda Vital (awal + observasi berulang) → Tindakan →
                                Resep → Keluhan Utama → Riwayat Penyakit → Riwayat Keluarga → Riwayat Pengobatan → Riwayat Perjalanan → Alergi →
                                Obat Diserahkan → <strong>Pemberian Obat</strong> → Laborat → Radiologi → Impresi Klinik → Penilaian → Nyeri &amp; Kesadaran →
                                Status Psikologis → Telaah Resep → Rencana Tindak Lanjut → <strong>Rencana Pemulangan</strong> → Kondisi Saat Keluar →
                                <strong>Finish Encounter</strong> → <strong>Resume Medis</strong>.
                            </p>
                            <p class="ds-body-sm mt-2" style="color:var(--muted)">
                                Kelas layanan IGD ("reguler") dipasang otomatis — tidak perlu diisi. Sumber tiap kartu yang sama dengan RJ: lihat
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)" x-on:click="go('ss-rj')">tabel Rawat Jalan</button>.
                            </p>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px; border-left:3px solid #f59e0b">
                            <div class="ds-title-sm mb-1">Urutan kerja yang disarankan di IGD</div>
                            <ol class="ds-body-sm space-y-1.5" style="list-style:decimal; padding-left:1.4em">
                                <li>Perawat: Jam Datang + tingkat triase di Pengkajian Perawat, lalu Screening → <strong>TTD</strong>.</li>
                                <li>Dokter: Waktu Pemeriksaan → anamnesa, diagnosa, resep → Tindak Lanjut + Kondisi Saat Keluar → Selesai Pemeriksaan.</li>
                                <li>Pasien MRS: kasir UGD melakukan <strong>Transfer ke RI</strong> (lihat Pasien Pindahan).</li>
                                <li>Mr: Coding SATUSEHAT → Kirim Semua.</li>
                            </ol>
                        </div>
                    </section>

                    {{-- ====== SATUSEHAT 3 — RAWAT INAP ====== --}}
                    <section x-show="section === 'ss-ri'" x-cloak>
                        <div class="ds-eyebrow mb-3">SATUSEHAT — Rawat Inap</div>
                        <h1 class="ds-display-md mb-4">Kirim SATUSEHAT Rawat Inap</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            "Rawat jalan itu foto, rawat inap itu video." Satu kunjungan RI berlangsung berhari-hari: pindah kamar,
                            CPPT harian, observasi berulang. Encounter dikirim <strong>sejak pasien masuk</strong> dan baru ditutup saat
                            <strong>tanggal pulang</strong> diisi.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'in-progress', 'judul' => 'Masuk RI', 'sub' => 'tanggal masuk + kamar pertama', 'chipWarna' => 'sky'],
                                ['chip' => 'pindah kamar', 'judul' => 'Kirim Encounter lagi', 'sub' => 'kamar baru ditambahkan, kamar lama ditutup', 'chipWarna' => 'amber'],
                                ['chip' => 'harian', 'judul' => 'CPPT, observasi, obat', 'sub' => 'kirim berkala'],
                                ['chip' => 'finished', 'judul' => 'Pulang', 'sub' => 'tanggal pulang (exitDate)', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Isian yang khas Rawat Inap</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Kartu</th><th>Diisi di mana</th><th>Oleh</th><th>Catatan</th></tr></thead>
                                    <tbody>
                                        @foreach ([
                                            ['Encounter', 'Pendaftaran RI: tanggal masuk, DPJP, kamar. Kamar harus punya <strong>Location</strong> (Master Kamar → Daftarkan Location)', 'Mr / IT', '<strong>Pindah kamar</strong>: tekan Kirim Encounter lagi — kamar baru ditambahkan dari riwayat pindah kamar'],
                                            ['Episode', 'Otomatis dari data RI', '—', 'Ditutup saat pulang (Finish Episode)'],
                                            ['Diagnosa', 'Diagnosa RI (ICD-10)', 'Dokter / Mr', 'Wajib untuk menutup kunjungan'],
                                            ['Pemeriksaan Fisik', 'Pengkajian Awal perawat (bagian pemeriksaan fisik) + observasi tanda vital berulang', 'Perawat', 'Tiap waktu ukur = satu kiriman'],
                                            ['Keluhan Utama &amp; Alergi', 'Teks: Pengkajian Dokter (cadangan Pengkajian Awal) · Kode: Coding SATUSEHAT RI', 'Dokter · Mr', 'Alergi dilewati bila Pengkajian Dokter belum mengisi'],
                                            ['Observasi Lanjutan', 'Obat &amp; cairan, pemakaian oksigen, pengeluaran cairan (urine)', 'Perawat', 'Obat tanpa KFA dilewati &amp; disebut'],
                                            ['CPPT', 'CPPT per profesi (SOAP)', 'PPA', '1 entri = 1 kiriman; penilai = DPJP'],
                                            ['Diet', 'Pengkajian Dokter → rencana diet', 'Dokter / Gizi', 'Teks bebas'],
                                            ['Edukasi', 'Edukasi terintegrasi', 'PPA', '—'],
                                            ['Perencanaan Pemulangan', 'Perencanaan RI → Discharge Planning (pelayanan berkelanjutan &amp; alat bantu)', 'Dokter / Perawat', 'Opsional'],
                                            ['Prognosis · Tindak Lanjut · Kondisi Saat Keluar', 'Perencanaan RI', 'Dokter', 'Kondisi Saat Keluar WAJIB'],
                                            ['Finish Encounter', '<strong>Tanggal pulang</strong> (exitDate) + diagnosa terkirim + status pulang (Tindak Lanjut)', 'Mr', 'Semua kamar ditutup otomatis'],
                                            ['Finish Episode', 'Tanggal pulang', 'Mr', 'Sesudah Finish Encounter'],
                                            ['Resume Medis', 'Otomatis', 'Mr', 'Paling akhir'],
                                        ] as $baris)
                                            <tr>
                                                <td class="ds-td-strong">{!! $baris[0] !!}</td>
                                                <td class="ds-body-sm">{!! $baris[1] !!}</td>
                                                <td class="ds-body-sm">{{ $baris[2] }}</td>
                                                <td class="ds-body-sm">{!! $baris[3] !!}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Pola kerja Mr selama dirawat</div>
                                <ol class="ds-body-sm space-y-1.5" style="list-style:decimal; padding-left:1.4em">
                                    <li><strong>Hari masuk</strong>: kirim Encounter (dan bila dari UGD, kunjungan otomatis tertaut ke UGD).</li>
                                    <li><strong>Setiap pindah kamar</strong>: kirim Encounter lagi.</li>
                                    <li><strong>Berkala</strong> (mis. harian): Kirim Semua — CPPT, observasi, obat baru ikut terkirim; yang lama tidak dobel.</li>
                                    <li><strong>Saat pulang</strong>: pastikan tanggal pulang &amp; Perencanaan lengkap → Kirim Semua sampai Resume Medis hijau.</li>
                                </ol>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Yang sering terlupa</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Kamar baru belum didaftarkan sebagai Location → Encounter ditolak.</li>
                                    <li>Tanggal pulang belum diisi → Finish ditolak ("jam sekarang tidak relevan").</li>
                                    <li>Kode keluhan utama/alergi belum dipilih di Coding SATUSEHAT RI.</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== SATUSEHAT 4 — PASIEN PINDAHAN ====== --}}
                    <section x-show="section === 'ss-transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">SATUSEHAT — Pasien Pindahan</div>
                        <h1 class="ds-display-md mb-4">Pasien Pindahan RJ → UGD → RI</h1>
                        <p class="ds-body-md mb-4" style="max-width:64ch">
                            Pasien poli yang dikirim ke IGD, lalu dirawat inap, tercatat sebagai <strong>tiga kunjungan</strong> di SATUSEHAT.
                            Supaya ketiganya tersambung (tidak "menghilang" di antara IGD dan bangsal), SIRUS memakai data
                            <strong>Transfer di kasir</strong>: setiap unit tujuan otomatis mengecek dari mana pasiennya datang.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'Kasir RJ', 'judul' => 'Transfer ke UGD', 'sub' => 'menyimpan tautan RJ → UGD', 'chipWarna' => 'sky'],
                                ['chip' => 'Kirim UGD', 'judul' => 'Encounter UGD', 'sub' => 'cek tautan → surat rujukan ke IGD dari RJ', 'chipWarna' => 'amber'],
                                ['chip' => 'Kasir UGD', 'judul' => 'Transfer ke RI', 'sub' => 'menyimpan tautan UGD → RI', 'chipWarna' => 'sky'],
                                ['chip' => 'Kirim RI', 'judul' => 'Encounter RI', 'sub' => 'cek tautan → surat rujukan rawat inap dari UGD', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Apa yang terjadi otomatis</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Saat</th><th>SIRUS mengecek</th><th>Kalau pasien pindahan</th><th>Kalau bukan</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Kirim Encounter UGD</td><td class="ds-body-sm">Apakah ada Transfer dari RJ?</td><td class="ds-body-sm">Dibuat surat rujukan ke IGD pada kunjungan RJ, lalu kunjungan UGD ditautkan ke surat itu. Notifikasi: "tertaut ke Rawat Jalan (RJ #…)"</td><td class="ds-body-sm">Kunjungan UGD biasa</td></tr>
                                        <tr><td class="ds-td-strong">Kirim Encounter RI</td><td class="ds-body-sm">Apakah ada Transfer dari UGD?</td><td class="ds-body-sm">Dibuat surat permintaan rawat inap pada kunjungan UGD, lalu kunjungan RI ditautkan. Notifikasi: "tertaut ke IGD (UGD #…)"</td><td class="ds-body-sm">Kunjungan RI biasa</td></tr>
                                        <tr><td class="ds-td-strong">Finish Encounter RJ</td><td class="ds-body-sm">Apakah pasien ditransfer ke UGD?</td><td class="ds-body-sm">Cara keluar = <strong>"Dipindahkan ke IGD (UGD #…)"</strong>; jam selesai boleh diambil dari <strong>Jam Datang</strong> Pengkajian Perawat UGD</td><td class="ds-body-sm">Cara keluar dari Tindak Lanjut</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:16px 20px; border-left:3px solid #f59e0b">
                            <div class="ds-title-sm mb-1">Urutan kirim yang benar</div>
                            <ol class="ds-body-sm space-y-1.5" style="list-style:decimal; padding-left:1.4em">
                                <li><strong>Kirim Encounter RJ dulu</strong>, baru UGD. Kirim Encounter <strong>UGD dulu</strong>, baru RI.</li>
                                <li>Kalau terbalik (unit asal belum dikirim), kunjungan tujuan tetap terkirim tetapi <strong>belum tersambung</strong> — notifikasi kuning menyebut nomor kunjungan asalnya. Tidak perlu panik: kirim kunjungan asal, lalu sambungan dipasang otomatis saat <strong>Finish Encounter</strong> unit tujuan.</li>
                                <li>Transfer harus lewat <strong>tombol Transfer di kasir</strong>. Pasien yang didaftarkan ulang manual di UGD/RI tidak punya tautan, sehingga tidak tersambung.</li>
                                <li>Untuk pasien RJ yang ditransfer, isi <strong>Jam Datang</strong> di Pengkajian Perawat UGD — tanpa itu kunjungan RJ-nya tidak bisa ditutup (pasien pindahan biasanya tidak punya jam penyerahan obat di RJ).</li>
                            </ol>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <div class="ds-title-sm mb-1">Bukan untuk pasien kontrol</div>
                            <div class="ds-body-sm">Kunjungan kontrol ulang di poli <strong>tidak</strong> disambungkan dengan cara ini — tiap kontrol adalah kunjungan baru. Penyambungan hanya untuk perpindahan unit lewat transfer kasir di hari yang sama.</div>
                        </div>
                    </section>

                    {{-- ====== SATUSEHAT 5 — ARTI PESAN ====== --}}
                    <section x-show="section === 'ss-masalah'" x-cloak>
                        <div class="ds-eyebrow mb-3">SATUSEHAT — Bantuan</div>
                        <h1 class="ds-display-md mb-4">Arti Pesan &amp; Cara Membereskan</h1>
                        <p class="ds-body-md mb-6" style="max-width:64ch">
                            Warna notifikasi: <strong style="color:#166534">hijau</strong> = berhasil ·
                            <strong style="color:#92400e">kuning</strong> = berhasil tapi ada yang kurang ·
                            <strong style="color:#991b1b">merah</strong> = ditolak, tidak terkirim ·
                            <strong>biru/info</strong> = dilewati (sudah pernah dikirim / opsional kosong).
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Pesan (kurang lebih)</th><th>Artinya</th><th>Yang harus dilakukan</th><th>Siapa</th></tr></thead>
                                    <tbody>
                                        @foreach ([
                                            ['Patient IHS Number kosong', 'Pasien belum terdaftar di SATUSEHAT', 'Master Pasien → tombol "Update patientUuid" (NIK harus 16 digit & benar)', 'Mr / Pendaftaran'],
                                            ['Dokter IHS (dr_uuid) kosong', 'Dokter belum punya nomor IHS', 'Master Dokter → isi IHS dokter', 'IT / Admin'],
                                            ['Location … kosong / Kamar belum punya Location UUID', 'Poli / IGD / kamar belum didaftarkan', 'Master Poli / Master Kamar → Daftarkan Location', 'IT / Admin'],
                                            ['Kirim Encounter terlebih dahulu', 'Kartu lain butuh kunjungan', 'Kirim kartu Encounter dulu (Kirim Semua sudah otomatis urut)', 'Mr'],
                                            ['Tanggal masuk … kosong', 'Data pendaftaran tidak lengkap', 'Betulkan tanggal di pendaftaran', 'Mr / Pendaftaran'],
                                            ['Jam selesai layanan (task 7 / task 5) belum tercatat', 'Kunjungan belum benar-benar selesai', 'Pastikan obat diserahkan di apotek / pasien sudah keluar poli. UGD: isi Selesai Pemeriksaan', 'Apoteker / Dokter'],
                                            ['Pasien dipindah ke UGD #…, tapi Jam Datang … belum diisi', 'Pasien RJ ditransfer, jam tiba di IGD kosong', 'Isi Jam Datang di Pengkajian Perawat UGD tujuan', 'Perawat UGD'],
                                            ['Tanggal pulang (exitDate) belum diisi', 'Pasien RI belum dipulangkan di sistem', 'Isi tanggal pulang (Administrasi RI)', 'Mr / Tu'],
                                            ['Kirim Diagnosa (Condition) dulu', 'Kunjungan tak boleh ditutup tanpa diagnosis', 'Isi diagnosa di EMR, kirim kartu Diagnosa', 'Dokter / Mr'],
                                            ['Encounter finished, tapi TANPA cara keluar', 'Tindak Lanjut belum dipilih', 'Isi Tindak Lanjut di Perencanaan (untuk kunjungan berikutnya)', 'Dokter'],
                                            ['… TANPA status triaged …', 'UGD: TTD Screening / tingkat triase belum ada saat dikirim', 'Lengkapi Screening &amp; triase <em>sebelum</em> kirim pada pasien berikutnya (tidak bisa ditambah belakangan)', 'Perawat UGD'],
                                            ['… tautan … belum dibuat (kirim Encounter RJ/UGD …)', 'Pasien pindahan, kunjungan asal belum dikirim', 'Kirim kunjungan asal, lalu Finish Encounter unit tujuan memasang sambungannya', 'Mr'],
                                            ['Kondisi Saat Keluar belum diisi … (wajib)', 'Isian wajib kosong', 'Perencanaan → Kondisi Saat Keluar', 'Dokter'],
                                            ['… sudah pernah dikirim', 'Bukan error — kartu ini sudah terkirim', 'Tidak perlu apa-apa', '—'],
                                            ['… belum berisi rencana (opsional) — dilewati', 'Isian opsional kosong', 'Boleh dibiarkan; isi bila memang ada rencana', '—'],
                                            ['Obat … tanpa KFA dilewati (disebut namanya)', 'Obat belum dipetakan ke KFA', 'Master Obat → isi kode KFA, lalu Kirim lagi', 'Farmasi'],
                                            ['API request failed: {"resourceType":"OperationOutcome" …}', 'Ditolak server SATUSEHAT', 'Salin pesannya (terutama bagian "text" dan "RuleNumber") dan laporkan ke IT', 'Mr → IT'],
                                            ['Koneksi / timeout', 'Server SATUSEHAT atau jaringan lambat', 'Tunggu sebentar, tekan Kirim lagi — kartu yang sudah hijau tidak dobel', 'Mr'],
                                        ] as $baris)
                                            <tr>
                                                <td class="ds-body-sm"><span class="ds-code">{{ $baris[0] }}</span></td>
                                                <td class="ds-body-sm">{{ $baris[1] }}</td>
                                                <td class="ds-body-sm">{!! $baris[2] !!}</td>
                                                <td class="ds-body-sm">{{ $baris[3] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Pertanyaan yang sering muncul</div>
                        <div class="ds-card-outline" style="padding:20px">
                            <dl class="ds-body-sm space-y-4">
                                <div><dt class="font-semibold">Salah isi lalu sudah terkirim, bagaimana?</dt><dd class="mt-1">Kiriman tidak bisa dibatalkan dari SIRUS. Betulkan EMR untuk arsip RS, lalu laporkan ke IT dengan nomor kunjungan dan kartu mana yang salah.</dd></div>
                                <div><dt class="font-semibold">Bolehkah menekan Kirim Semua berkali-kali?</dt><dd class="mt-1">Boleh. Kartu yang sudah hijau dilewati; hanya yang belum terkirim yang dicoba lagi.</dd></div>
                                <div><dt class="font-semibold">Kenapa ada kartu yang tidak terkirim padahal EMR terisi?</dt><dd class="mt-1">Biasanya karena kodenya belum dipilih di layar Coding SATUSEHAT, atau master (obat KFA, item lab LOINC, Location kamar) belum lengkap. Baca pesannya — selalu menyebut yang kurang.</dd></div>
                                <div><dt class="font-semibold">Pasien kontrol rutin — apakah disambung dengan kunjungan sebelumnya?</dt><dd class="mt-1">Tidak. Setiap kontrol adalah kunjungan baru. Sambungan otomatis hanya untuk transfer RJ → UGD → RI.</dd></div>
                                <div><dt class="font-semibold">Di mana melihat apa yang benar-benar terkirim?</dt><dd class="mt-1">Per pasien: chip hijau dan tulisan "Terkirim" di kartu; tombol pratinjau di kartu menampilkan isinya. Seluruh RS: Monitoring SATUSEHAT di menu Manajemen.</dd></div>
                            </dl>
                        </div>
                    </section>
