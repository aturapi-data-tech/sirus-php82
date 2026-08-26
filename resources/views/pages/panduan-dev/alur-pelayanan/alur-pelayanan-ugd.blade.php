                    {{-- ====== UGD 1 — DAFTAR ====== --}}
                    <section x-show="section === 'ugd-daftar'" x-cloak>
                        <div class="ds-eyebrow mb-3">UGD — Tahap 1</div>
                        <h1 class="ds-display-md mb-4">Daftar UGD (Pendaftaran)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Pasien gawat darurat datang <strong>tanpa booking dan tanpa poli</strong>, jadi
                            pendaftaran dibuat seringkas mungkin — pelayanan tidak menunggu administrasi
                            lengkap. Menu: <span class="ds-code">/ugd/daftar</span>
                            (Mr, Admin, Supervisor Tu, Manager Umum).
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => null, 'judul' => 'Pasien datang', 'sub' => 'tanpa booking / rujukan'],
                                ['chip' => 'Mr/Tu', 'judul' => 'Input pendaftaran', 'sub' => 'pasien · cara masuk · dokter jaga · klaim', 'chipWarna' => 'sky'],
                                ['chip' => 'BPJS', 'judul' => 'SEP UGD', 'sub' => 'VClaim — bisa menyusul'],
                                ['chip' => 'A', 'judul' => 'Langsung dilayani', 'sub' => 'tanpa antre poli', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Yang diisi saat mendaftar</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li>Cari pasien dari Master Pasien — pasien tak dikenal/tak sadar bisa
                                    didaftarkan dengan data minimal dulu, dilengkapi belakangan.</li>
                                <li><strong>Cara masuk</strong> (datang sendiri / rujukan — daftar dari master
                                    cara masuk, menentukan status rujukan) + <strong>dokter jaga</strong> +
                                    <strong>klaim</strong> (UMUM/BPJS) + shift.</li>
                                <li>Pasien BPJS: buat <strong>SEP</strong> dari aksi baris (VClaim) —
                                    boleh menyusul setelah pasien tertangani.</li>
                            </ol>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Aksi per baris — sama keluarga dengan Daftar RJ</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <tbody>
                                        @foreach ([
                                            ['Edit Pendaftaran', 'Lengkapi/ubah data; batal daftar'],
                                            ['SEP / VClaim', 'Buat & kelola SEP UGD'],
                                            ['Diagnosa · iDRG · SATUSEHAT · Berkas BPJS', 'Sama seperti RJ — klaim & pelaporan'],
                                            ['Info kelengkapan EMR', 'Persentase EMR + bagian yang belum terisi'],
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

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Satu kunjungan UGD = satu baris <span class="ds-code">rstxn_ugdhdrs</span> +
                                JSON <span class="ds-code">datadaftarugd_json</span> — pola kembar dengan RJ.
                                Kolom status di list menyesuaikan role: petugas pendaftaran melihat
                                <span class="ds-code">rj_status</span>, dokter/perawat melihat
                                <span class="ds-code">erm_status</span>. Badge <strong>triase</strong> ikut
                                tampil di list begitu perawat mengisinya.
                            </span>
                        </div>
                    </section>

                    {{-- ====== UGD 2 — PELAYANAN ====== --}}
                    <section x-show="section === 'ugd-pelayanan'" x-cloak>
                        <div class="ds-eyebrow mb-3">UGD — Tahap 2</div>
                        <h1 class="ds-display-md mb-4">Pelayanan UGD (EMR)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Worklist dokter &amp; perawat UGD (<span class="ds-code">/ugd/pelayanan</span>) —
                            filter <span class="ds-code">erm_status</span>: A = Proses Dilayani (default),
                            L = Selesai. Pelayanan selalu dibuka dengan <strong>triase</strong>: memilah
                            kegawatan sebelum apa pun.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'Triase', 'judul' => 'P0–P3', 'sub' => 'tingkat kegawatan', 'chipWarna' => 'red'],
                                ['chip' => 'Perawat', 'judul' => 'Screening & penilaian', 'sub' => 'TTV · nyeri · risiko jatuh', 'chipWarna' => 'sky'],
                                ['chip' => 'Dokter', 'judul' => 'Asesmen & diagnosa', 'sub' => 'status medik · ICD-10', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Tindakan', 'sub' => 'obat & cairan · observasi berkala'],
                                ['chip' => null, 'judul' => 'Tindak lanjut', 'sub' => 'ujung kunjungan', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Triase — kartu pasien diberi warna kegawatan</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Tingkat</th>
                                            <th>Arti</th>
                                            <th>Warna di list</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['P1', 'Kritis — ditangani segera', 'Merah'],
                                            ['P2', 'Urgent', 'Kuning'],
                                            ['P3', 'Minor', 'Hijau'],
                                            ['P0', 'Death on arrival', 'Hitam/abu gelap'],
                                        ] as [$tingkat, $arti, $warna])
                                            <tr>
                                                <td class="ds-td-token">{{ $tingkat }}</td>
                                                <td class="ds-td-strong">{{ $arti }}</td>
                                                <td>{{ $warna }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Isi EMR UGD — tab khasnya beda dari RJ</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Tab</th>
                                            <th>Isi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Screening', 'Skrining awal + tanda vital'],
                                            ['Anamnesa', 'Pengkajian perawat (termasuk TRIASE & alergi) + Status Medik dokter'],
                                            ['Pemeriksaan', 'Fisik + order Lab/Radiologi (cabang sama dengan RJ)'],
                                            ['Penilaian', 'Nyeri, risiko jatuh, skrining gizi, risiko bunuh diri'],
                                            ['Diagnosa', 'ICD-10'],
                                            ['Obat & Cairan', 'KHAS UGD — pemberian obat/infus langsung saat tindakan'],
                                            ['Observasi', 'KHAS UGD — observasi berkala (TTV per jam)'],
                                            ['Perencanaan', 'Tindak lanjut: MRS · Kontrol · Rujuk · Perawatan Selesai · PRB · Meninggal · Lain-lain'],
                                            ['Rujukan antar RS', 'KHAS UGD — rujukan berbasis kompetensi'],
                                            ['Modul Dokumen & E-Resep', 'Sama seperti RJ'],
                                        ] as [$tab, $isi])
                                            <tr>
                                                <td class="ds-td-token">{{ $tab }}</td>
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
                                Tindak lanjut menentukan ujung kunjungan: <strong>MRS</strong> membuka modal
                                <em>Transfer ke Rawat Inap</em> (pilih kamar + penjaminan; form dua sisi —
                                perawat UGD mengirim, perawat ruangan menerima), <strong>Meninggal</strong>
                                tercatat sebagai death-on-IGD, sisanya menutup kunjungan seperti RJ.
                            </span>
                        </div>
                    </section>

                    {{-- ====== UGD 3 — APOTEK ====== --}}
                    <section x-show="section === 'ugd-apotek'" x-cloak>
                        <div class="ds-eyebrow mb-3">UGD — Tahap 3</div>
                        <h1 class="ds-display-md mb-4">Apotek UGD</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Antrian farmasi khusus pasien UGD
                            (<span class="ds-code">/transaksi/ugd/antrian-apotek-ugd</span>, atau tab UGD di
                            <span class="ds-code">/transaksi/apotek</span>) — polanya <strong>identik dengan
                            Apotek RJ</strong>: filter default "belum serah obat", telaah resep &amp; telaah
                            obat ber-TTD, stempel taskId 6/7.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'taskId 6', 'judul' => 'Resep masuk', 'sub' => 'e-resep UGD'],
                                ['chip' => 'TTD', 'judul' => 'Telaah resep', 'sub' => 'apoteker', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Siapkan obat', 'sub' => 'harga → pos Obat'],
                                ['chip' => 'TTD', 'judul' => 'Telaah obat', 'sub' => '5T', 'chipWarna' => 'sky'],
                                ['chip' => 'taskId 7', 'judul' => 'Serah obat', 'sub' => 'selesai', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Bedanya dari RJ: obat &amp; cairan yang dipakai <em>selama tindakan</em> sudah
                                dicatat perawat/dokter di tab <strong>Obat &amp; Cairan</strong> EMR dan ikut
                                tertagih sebagai obat UGD — antrian apotek terutama melayani
                                <strong>resep pulang</strong> pasien.
                            </span>
                        </div>
                    </section>

                    {{-- ====== UGD 4 — KASIR ====== --}}
                    <section x-show="section === 'ugd-kasir'" x-cloak>
                        <div class="ds-eyebrow mb-3">UGD — Tahap 4</div>
                        <h1 class="ds-display-md mb-4">Kasir UGD</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Antrian kasir khusus pasien UGD
                            (<span class="ds-code">/transaksi/ugd/antrian-kasir-ugd</span>, atau tab UGD di
                            <span class="ds-code">/transaksi/kasir</span>) → buka layar
                            <strong>Administrasi UGD</strong>. Alur pembayarannya sama dengan Kasir RJ; yang
                            beda susunan pos biayanya.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'otomatis', 'judul' => 'Pos biaya terkumpul', 'sub' => 'tindakan · obat · penunjang'],
                                ['chip' => null, 'judul' => 'Periksa ringkasan', 'sub' => 'Total − Diskon − Sudah Bayar'],
                                ['chip' => 'kasir', 'judul' => 'Terima pembayaran', 'sub' => 'atau transfer ke RI (MRS)', 'chipWarna' => 'sky'],
                                ['chip' => 'rj_status L', 'judul' => 'Kwitansi & tutup', 'sub' => 'jurnal kas terbentuk', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Pos biaya Administrasi UGD</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="flex flex-wrap gap-2">
                                @foreach (['RS Admin', 'Admin OB', 'Uang Periksa', 'Jasa Karyawan', 'Jasa Dokter', 'Jasa Medis', 'Obat', 'Laboratorium', 'Radiologi', 'Kamar Operasi', 'Lain-lain', 'Transfer (biaya RJ ikut)'] as $pos)
                                    <span class="px-2.5 py-1 text-sm rounded-full"
                                        style="background:var(--surface-card); color:var(--body)">{{ $pos }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Pasien <strong>MRS</strong> tidak membayar di UGD: kunjungan ditutup sebagai
                                transfer dan seluruh biaya UGD dibawa ke Rawat Inap (pos "Trf UGD/RJ" di
                                Administrasi RI) — pasien membayar sekali di ujung perawatan.
                            </span>
                        </div>
                    </section>