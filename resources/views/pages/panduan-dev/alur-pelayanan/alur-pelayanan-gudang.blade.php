                    {{-- ====== GUDANG — PENERIMAAN ====== --}}
                    <section x-show="section === 'gudang-penerimaan'" x-cloak>
                        <div class="ds-eyebrow mb-3">Gudang</div>
                        <h1 class="ds-display-md mb-4">Penerimaan Barang Medis &amp; Non-Medis</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Alur di luar pelayanan pasien tapi menghidupinya: <strong>stok</strong>. Barang
                            datang dari PBF/supplier beserta faktur → dientri sebagai penerimaan →
                            di-<strong>posting</strong> → stok gudang bertambah dan siap didistribusikan
                            (Transfer Stok) ke apotek/ruangan. Modul non-medis adalah kembaran modul medis —
                            alurnya sama, beda gudang, barang, dan role.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => null, 'judul' => 'Barang + faktur datang', 'sub' => 'dari PBF / supplier'],
                                ['chip' => 'Gudang', 'judul' => 'Entri penerimaan', 'sub' => 'supplier · item · qty · harga beli', 'chipWarna' => 'sky'],
                                ['chip' => 'cek', 'judul' => 'Harga vs master', 'sub' => 'beda? tawarkan update master', 'chipWarna' => 'amber'],
                                ['chip' => null, 'judul' => 'Hitung total', 'sub' => 'Diskon → PPN → Materai → Bayar'],
                                ['chip' => 'posting', 'judul' => 'Simpan & Posting', 'sub' => 'Lunas / Hutang', 'chipWarna' => 'sky'],
                                ['chip' => 'stok +', 'judul' => 'Masuk gudang', 'sub' => 'siap ditransfer', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Medis vs Non-Medis</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Aspek</th>
                                            <th>Medis — "Obat dari PBF"</th>
                                            <th>Non-Medis — "Barang dari Supplier"</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Menu', '/gudang/penerimaan-medis', '/gudang/penerimaan-non-medis'],
                                            ['Role', 'Gudang Obat, Apoteker, Admin, Manager Umum, Supervisor Tu', 'Gudang Non Medis, Tu, Admin, Manager Umum, Supervisor Tu'],
                                            ['Barang', 'Obat & alkes (master produk medis)', 'ATK, rumah tangga, dan barang umum lainnya'],
                                            ['Stok masuk ke', 'Gudang Medis', 'Gudang Non-Medis'],
                                            ['Distribusi lanjutan', 'Transfer Stok Medis (→ Apotek / ruangan)', 'Transfer Stok Non-Medis (→ unit)'],
                                            ['Audit mutasi', 'Kartu Stock Gudang Medis & Apotek', 'Kartu Stock Non-Medis'],
                                        ] as [$aspek, $medis, $non])
                                            <tr>
                                                <td class="ds-td-token">{{ $aspek }}</td>
                                                <td>{{ $medis }}</td>
                                                <td>{{ $non }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Langkah petugas gudang</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ol class="ds-body-md space-y-3" style="list-style:decimal; padding-left:1.4em">
                                <li><strong>Penerimaan baru</strong> → pilih <strong>supplier</strong> (LOV
                                    master supplier), isi nomor/tanggal faktur.</li>
                                <li>Tambah barang per item: produk (LOV master), qty, <strong>harga
                                    beli</strong>. Bila harga beli beda dari harga master, sistem menawarkan
                                    <strong>update harga master</strong> — boleh diterima atau dilewati.</li>
                                <li>Urutan penutup yang dipandu form: <strong>Diskon → PPN → Materai →
                                    Bayar → Akun Kas → Simpan &amp; Posting</strong>.</li>
                                <li>Jumlah bayar menentukan status: bayar penuh = <strong>Lunas</strong>,
                                    kurang = <strong>Hutang</strong> (dilunasi belakangan lewat tombol
                                    Bayar pada transaksi tersebut).</li>
                            </ol>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Status penerimaan</div>
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
                                            ['A', 'Daftar Tunggu', 'Masih bisa di-edit / dihapus — belum posting'],
                                            ['L', 'Lunas', 'Final — stok & pembayaran terposting'],
                                            ['H', 'Hutang', 'Final — stok terposting, sisa tagihan menunggu pelunasan'],
                                            ['F', 'Batal', 'Dibatalkan; bisa dihapus'],
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

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Hubungan dengan Kartu Stock — buku besar stok per produk</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'per tahun', 'judul' => 'Saldo awal', 'sub' => 'saldo pembukaan produk'],
                                ['chip' => 'RCV +', 'judul' => 'Penerimaan PBF', 'sub' => 'posting modul ini → masuk', 'chipWarna' => 'green'],
                                ['chip' => '±', 'judul' => 'Transfer stok', 'sub' => 'gudang ↔ apotek/ruangan', 'chipWarna' => 'sky'],
                                ['chip' => 'SLS/RJ −', 'judul' => 'Keluar via e-resep', 'sub' => 'resep RJ/UGD/RI · obat bebas', 'chipWarna' => 'amber'],
                                ['chip' => 'SO', 'judul' => 'Stock opname', 'sub' => 'koreksi stok fisik'],
                                ['chip' => '=', 'judul' => 'Saldo akhir', 'sub' => 'terlihat di Kartu Stock', 'chipWarna' => 'green'],
                            ]])
                            <p class="ds-body-sm mt-4" style="color:var(--muted-soft)">
                                Kartu Stock (menu Gudang, read-only) = <strong>buku besar per produk per
                                tahun</strong>: saldo awal + seluruh mutasi masuk/keluar = saldo akhir. Setiap
                                <em>Simpan &amp; Posting</em> di modul penerimaan otomatis menulis satu baris
                                mutasi berlabel <strong>RCV — Beli PBF</strong> (masuk); label lain: SLS obat
                                bebas, RJ pelayanan rawat jalan, SO stock opname. Ada tiga kartu sesuai
                                lokasinya: Gudang Medis, Apotek, dan Non-Medis — dan koreksi
                                <strong>stock opname</strong> juga diinput dari layar kartu ini.
                            </p>
                            <p class="ds-body-sm mt-2" style="color:var(--muted-soft)">
                                Rantai lengkap satu butir obat: <strong>RCV</strong> masuk Gudang Medis →
                                <strong>Transfer Stok</strong> memindahkannya ke Apotek (atau ruangan) →
                                keluar dari Apotek saat <strong>e-resep dilayani</strong> (RJ/UGD/RI —
                                menjadi pos Obat di administrasi pasien) atau terjual bebas (SLS).
                                ⚠️ Catatan: <strong>ruangan tidak punya kartu stok sendiri</strong> —
                                transfer ke ruangan tercatat sebagai keluar dari gudang/apotek, tapi
                                pemakaian di ruangan tidak ber-ledger per produk.
                            </p>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Model datanya header–detail
                                (<span class="ds-code">imtxn_receivehdrs</span> +
                                <span class="ds-code">imtxn_receivedtls</span>, master
                                <span class="ds-code">immst_suppliers</span> /
                                <span class="ds-code">immst_products</span>); ledger mutasi dibaca dari view
                                <span class="ds-code">tkview_iostockwhs</span> + saldo awal
                                <span class="ds-code">tktxn_saldoawalstocks</span>. Setelah posting, transaksi
                                terkunci (edit hanya di status Daftar Tunggu) — koreksi lewat Batal, dan
                                semua mutasi stok terlacak di Kartu Stock.
                            </span>
                        </div>
                    </section>

                    {{-- ====== GUDANG — TRANSFER STOK ====== --}}
                    <section x-show="section === 'gudang-transfer'" x-cloak>
                        <div class="ds-eyebrow mb-3">Gudang</div>
                        <h1 class="ds-display-md mb-4">Transfer Stok (Distribusi)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Jembatan antara penerimaan dan pemakaian: memindahkan barang dari gudang ke titik
                            pakainya. Medis: <span class="ds-code">/gudang/transfer-stock</span> — sumber
                            <strong>Gudang Medis</strong> atau <strong>Apotek</strong> (dua tab), tujuan bebas
                            dipilih dari <strong>Master Lokasi Stok</strong> (±40 lokasi: apotek, UGD, ICU, VK,
                            OK, bangsal, laborat, dapur…). Non-medis kembarannya:
                            <span class="ds-code">/gudang/transfer-stock-non</span>.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => null, 'judul' => 'Pilih sumber', 'sub' => 'Gudang Medis · Apotek'],
                                ['chip' => 'LOV', 'judul' => 'Pilih tujuan', 'sub' => 'lokasi mana pun (Master Lokasi Stok)', 'chipWarna' => 'sky'],
                                ['chip' => 'A', 'judul' => 'Isi item & qty', 'sub' => 'draft — masih bisa diubah/dihapus', 'chipWarna' => 'amber'],
                                ['chip' => 'L', 'judul' => 'Posting', 'sub' => 'stok sumber berkurang', 'chipWarna' => 'green'],
                                ['chip' => 'F', 'judul' => 'Batal', 'sub' => 'bila salah — hanya dari posted', 'chipWarna' => 'red'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Status transfer (imtxn_trfhdrs)</div>
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
                                            ['A', 'Draft', 'Item & qty masih bisa diubah; bisa dihapus utuh'],
                                            ['L', 'Posted', 'Stok sumber terpotong — final'],
                                            ['F', 'Batal', 'Pembatalan transfer yang terlanjur posted'],
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
                                Kaitan ledger: posting menulis mutasi <strong>keluar</strong> di ledger lokasi
                                sumber; tujuan <strong>Apotek</strong> tercatat masuk di ledger apotek
                                (rantai RCV → TRF → e-resep tetap utuh). Tujuan <strong>ruangan</strong>:
                                pengiriman tercatat, tapi ledger berhenti di situ — ruangan belum ber-kartu
                                stok (lihat catatan di seksi Penerimaan). Model data:
                                <span class="ds-code">imtxn_trfhdrs</span> +
                                <span class="ds-code">imtxn_trfdtls</span>, qty + tanggal ED per baris.
                            </span>
                        </div>
                    </section>