                    {{-- ====== 10 RANJAU UMUM ====== --}}
                    <section x-show="section === 'ranjau'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Ranjau Umum (Livewire + Oracle + Blade)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Jebakan yang <strong>sudah pernah menggigit</strong> di repo ini — masing-masing
                            pernah jadi bug produksi atau debugging berjam-jam. Kenali gejalanya;
                            penangkalnya sudah terstandar.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead>
                                    <tr><th>Ranjau</th><th>Gejala</th><th>Penangkal</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ([
                                        ['wire:model.live di input numerik', 'digit hilang saat mengetik cepat (race roundtrip)', 'wire:model.blur utk numerik EMR; auto-calc di updated()'],
                                        ['keyup.enter + aksi $wire', 'insert dobel / nilai belum tersinkron saat Enter', 'keydown.enter.prevent + $el.blur() lalu $wire.aksi() (+ .then() refocus)'],
                                        ['Reload DB lalu $this->state = $data', 'ketikan yang belum di-Simpan ikut terhapus', 'array_replace(state lama, data DB) — jangan replace mentah'],
                                        ["Oracle: string kosong = NULL", "where col <> '' selalu 0 baris", "IS NOT NULL / LENGTH(TRIM(x)) > 0"],
                                        ['Kolom mixed-case (dari API)', 'ORA-00904 padahal kolom ada', 'DB::raw(\'"requestTransferTime" as alias_snake\')'],
                                        ['JSON_VALUE di query', 'ORA-00904 — fungsi tak dikenal di Oracle versi ini', 'INSTR utk filter kasar, atau json_decode di PHP'],
                                        ["active_status master lama", "filter 'Y'/'N' tidak mengembalikan apa pun", "nilai sebenarnya '1'/'0'"],
                                        ['Carbon 3: diffInSeconds(x, false)', 'tanda +/- kebalik dari Carbon 2', '$end->getTimestamp() - $start->getTimestamp()'],
                                        [chr(64) . 'if di dalam atribut komponen x-*', 'ParseError saat compile', 'rakit string di blok php, lalu render via kurung kurawal ganda di atribut'],
                                        ['Tag komponen dipecah antar ' . chr(64) . 'if', 'konten hilang diam-diam saat cabang skip', 'ekstrak jadi sub-komponen utuh per cabang'],
                                        ['Literal tag penutup php di string/nowdoc', 'kelas Volt terpotong → ParseError 500', 'tandai batas dgn komentar; pastikan grep tag penutup = 1'],
                                        ['Kata "re-use"/"reuse" di komentar //', 'Volt salah strip komentar → ParseError', 'hindari kata itu di komentar file Volt'],
                                        ['Call API BPJS sinkron tanpa timeout', 'seluruh worker app membeku', 'Http::timeout(8)->connectTimeout(3)'],
                                        ['Umur dari kolom thn/bln/hari', 'umur pasien basi (snapshot lama)', 'selalu hitung dari birth_date'],
                                    ] as [$ranjau, $gejala, $obat])
                                        <tr>
                                            <td class="ds-td-strong">{{ $ranjau }}</td>
                                            <td class="ds-body-sm">{{ $gejala }}</td>
                                            <td class="ds-body-sm"><span class="ds-code">{{ $obat }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Daftar hidupnya ada di skill repo: <span class="ds-code">oracle-quirks</span> ·
                                <span class="ds-code">livewire-input-patterns</span> ·
                                <span class="ds-code">blade-safe-edit</span> ·
                                <span class="ds-code">master-pasien</span> — plus
                                <span class="ds-code">docs/*.md</span> per pola. Kalau menemukan ranjau baru,
                                tambahkan ke sini &amp; ke skill-nya.
                            </span>
                        </div>
                    </section>

                    {{-- ====== 11 CHECKLIST ADOPSI ====== --}}
                    <section x-show="section === 'adopsi'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Checklist Adopsi</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Mau menambah tahap di jalur yang ada, atau mengadopsi pola transaksi
                            untuk jalur/layanan baru? Ikuti kerangka folder ini lalu centang checklist-nya.
                        </p>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Kerangka folder satu jalur</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['adopsi-tree'] }}</pre>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:24px">
                            <ul class="ds-body-sm space-y-2.5">
                                @foreach ([
                                    'Tabel header + kolom JSON CLOB dirancang dulu (PK, kolom datadaftar*_json) — sepakati struktur JSON per section',
                                    'Trait jalur dibuat mengikuti EmrRJTrait: findData / lockRow / updateJson / appendAdminLog / checkStatus',
                                    'Baca CLOB SELALU via OracleLob::read; tulis SELALU transaction + lock (bab 03)',
                                    'List: baseQuery subquery ter-scope tanggal + paginate DB + transform page aktif; poll hanya di antrian',
                                    'EMR host = modal + section child ber-:id + save-events broadcast + silent toast',
                                    'Setiap form dokumen: Draft → TTD-kunci; teks klausul via *Clause versioning',
                                    'Administrasi: satu file per pos + sumAll(); selesai → status utk antrian kasir',
                                    'Mutasi uang: transaction + lockForUpdate + guard role kasir + dukung bon',
                                    'Semua aksi admin/MR tercatat appendAdminLog* (kategori ADMIN/MR)',
                                    'API eksternal (BPJS dkk): trait per-API pola VclaimTrait + timeout wajib',
                                    'Jangan blind-copy antar jalur: UGD punya triase/transfer; RI tanpa pelayanan & billing per-item',
                                    'Git: kerjakan di branch develop / feature branch → PR; branch main menolak merge commit (fast-forward only)',
                                    'Ikuti juga seluruh checklist Tutorial Koding Master (komponen, event, validasi, LOV)',
                                ] as $item)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="w-4 h-4 mt-0.5 shrink-0" style="color:var(--primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>

                    {{-- ====== 12 TRAIT & REFERENSI ====== --}}
                    <section x-show="section === 'referensi'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Trait &amp; Referensi</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Peta trait di <span class="ds-code">app/Http/Traits/</span> yang menopang transaksi —
                            kenali dulu sebelum menulis helper baru (kemungkinan besar sudah ada).
                        </p>

                        <div class="ds-card-dark mb-6" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola trait API eksternal — SEP · task-id antrean · SATU SEHAT</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['api-trait'] }}</pre>
                        </div>

                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead><tr><th>Trait / helper</th><th>Peran</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-class">Txn/{Rj,Ugd,Ri}/Emr*Trait</td><td class="ds-body-sm">inti jalur: findData, lockRow, updateJson, appendAdminLog, cek lock</td></tr>
                                    <tr><td class="ds-td-class">Txn/*/EmrCompleteness*Trait</td><td class="ds-body-sm">% kelengkapan EMR (bobot SOAP per jalur) utk progress list</td></tr>
                                    <tr><td class="ds-td-class">App\Support\OracleLob</td><td class="ds-body-sm">baca CLOB aman (anti ORA-01555 / truncate 32k) — helper statis</td></tr>
                                    <tr><td class="ds-td-class">Master/MasterPasien/MasterPasienTrait</td><td class="ds-body-sm">findDataMasterPasien(regNo) — identitas, BPJS, alamat</td></tr>
                                    <tr><td class="ds-td-class">BPJS/{Vclaim,Antrian,Aplicares,iCare}Trait</td><td class="ds-body-sm">SEP/rujukan · antrean+task-id · ketersediaan TT · riwayat i-Care</td></tr>
                                    <tr><td class="ds-td-class">iDRG/iDrgTrait</td><td class="ds-body-sm">grouping casemix iDRG (klaim)</td></tr>
                                    <tr><td class="ds-td-class">SATUSEHAT/*</td><td class="ds-body-sm">kirim EMR ke Satu Sehat (FHIR: Encounter, Condition, dst.)</td></tr>
                                    <tr><td class="ds-td-class">Dokumen/DokumenViewSupportTrait</td><td class="ds-body-sm">viewer/cetak dokumen RM</td></tr>
                                    <tr><td class="ds-td-class">WithValidationToast/WithValidationToastTrait</td><td class="ds-body-sm">validateWithToast() — toast otomatis saat validasi gagal</td></tr>
                                    <tr><td class="ds-td-class">WithRenderVersioning/WithRenderVersioningTrait</td><td class="ds-body-sm">remount granular per-area (toolbar/modal)</td></tr>
                                    <tr><td class="ds-td-class">Stock/StockBalanceTrait</td><td class="ds-body-sm">saldo stok obat (apotek/administrasi)</td></tr>
                                    <tr><td class="ds-td-class">App\Support\LogText</td><td class="ds-body-sm">sanitasi teks log (helper statis — anti tabrakan trait)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="ds-title-lg mt-10 mb-4">Dokumen terkait</h2>
                        <div class="ds-card-outline" style="padding:0; overflow:hidden">
                            <table class="ds-table">
                                <thead><tr><th>Topik</th><th>Baca</th></tr></thead>
                                <tbody>
                                    <tr><td class="ds-td-strong">Standar modul master (prasyarat)</td><td class="ds-td-class">docs/standar-master-module.md + /panduan-dev/koding-master</td></tr>
                                    <tr><td class="ds-td-strong">Trait API eksternal</td><td class="ds-td-class">docs/trait-template-api-eksternal.md</td></tr>
                                    <tr><td class="ds-td-strong">Bridging iDRG</td><td class="ds-td-class">docs/idrg-bridging.md</td></tr>
                                    <tr><td class="ds-td-strong">Diagnosa ICD-10</td><td class="ds-td-class">docs/diagnosa-architecture.md (+ skill diagnosa-flow)</td></tr>
                                    <tr><td class="ds-td-strong">Clause versioning dokumen</td><td class="ds-td-class">docs/clause-versioning.md (+ skill clause-versioning)</td></tr>
                                    <tr><td class="ds-td-strong">Viewer dokumen (Lihat)</td><td class="ds-td-class">docs/dokumen-view-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">TTD cetak PDF / TTD petugas</td><td class="ds-td-class">docs/ttd-pattern-pdf-print.md · docs/ttd-petugas-component.md</td></tr>
                                    <tr><td class="ds-td-strong">Lookup list stabil</td><td class="ds-td-class">docs/stable-lookup-list-pattern.md</td></tr>
                                    <tr><td class="ds-td-strong">Jebakan Oracle & input Livewire</td><td class="ds-td-class">skill oracle-quirks · skill livewire-input-patterns</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {{-- ====== 13 GLOSARIUM ====== --}}
                    <section x-show="section === 'glosarium'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Glosarium Istilah</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Domain rumah sakit penuh singkatan. Kalau menemukan istilah asing di
                            tutorial, kode, atau rapat — cari di sini dulu.
                        </p>

                        <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                            <table class="ds-table">
                                <thead><tr><th>Istilah</th><th>Arti</th></tr></thead>
                                <tbody>
                                    @foreach ([
                                        ['RJ · UGD · RI', 'Tiga jalur pelayanan: Rawat Jalan, Unit Gawat Darurat, Rawat Inap'],
                                        ['No RM / reg_no', 'Nomor rekam medis — identitas pasien seumur hidup (satu per orang)'],
                                        ['rj_no / rihdr_no', 'Nomor transaksi kunjungan — satu per kedatangan (bukan per pasien)'],
                                        ['DPJP', 'Dokter Penanggung Jawab Pelayanan — dokter utama pasien'],
                                        ['EMR / ERM', 'Rekam medis elektronik — modal SOAP di halaman pelayanan'],
                                        ['SOAP', 'Subjective · Objective · Assessment · Plan — struktur pemeriksaan klinis'],
                                        ['CPPT', 'Catatan Perkembangan Pasien Terintegrasi — catatan harian multi-profesi di RI'],
                                        ['SBAR', 'Situation Background Assessment Recommendation — format komunikasi perawat→dokter'],
                                        ['Askep', 'Asuhan Keperawatan — diagnosis & intervensi perawat (standar SDKI/SLKI/SIKI)'],
                                        ['Triase P0–P3', 'Prioritas kegawatan pasien UGD (P0 resusitasi ... P3 ringan)'],
                                        ['SEP', 'Surat Eligibilitas Peserta — dokumen BPJS wajib agar kunjungan bisa diklaim'],
                                        ['VClaim', 'Web-service BPJS utk SEP, rujukan, surat kontrol'],
                                        ['MJKN', 'Mobile JKN — aplikasi booking antrean online BPJS'],
                                        ['Task-id', 'Stempel waktu tahapan pelayanan yang dilaporkan ke antrean BPJS (taskId 3–7; 99 = batal)'],
                                        ['PRB', 'Program Rujuk Balik — pasien kronis stabil ambil obat rutin di faskes 1'],
                                        ['SKDP', 'Surat Keterangan Dalam Perawatan — surat kontrol utk kunjungan berikutnya'],
                                        ['iDRG / INA-CBG', 'Sistem grouping tarif klaim Kemenkes (aplikasi E-Klaim); iDRG menggantikan INA-CBG'],
                                        ['Casemix', 'Unit pengelola koding & klaim (jembatan medis ↔ administrasi)'],
                                        ['SATU SEHAT', 'Platform interoperabilitas data kesehatan Kemenkes (standar FHIR)'],
                                        ['SIRS / Aplicares', 'Pelaporan RS Online Kemenkes / ketersediaan tempat tidur ke BPJS'],
                                        ['Klaim ID', 'Kode penjamin kunjungan (UMUM, BPJS, karyawan, dst.) — kolom klaim_id'],
                                        ['Bon', 'Pembayaran kurang dari total tagihan — sisa jadi piutang pasien'],
                                        ['Etiket', 'Label cetak kecil — identitas pasien (gelang/sampel) atau aturan pakai obat'],
                                        ['PTO', 'Pemantauan Terapi Obat — telaah apoteker utk resep RI'],
                                        ['Bangsal · Kamar · Bed', 'Hierarki tempat tidur RI (bangsal → kamar → bed)'],
                                        ['Shift', 'Pembagian waktu jaga; lookup tabel rstxn_shiftctls berdasar jam sekarang'],
                                        ['CLOB', 'Kolom teks besar Oracle — tempat JSON detail kunjungan disimpan'],
                                        ['LOV', 'List of Values — komponen pencarian data master (ketik → pilih)'],
                                    ] as [$istilah, $arti])
                                        <tr>
                                            <td class="ds-td-strong" style="white-space:nowrap">{{ $istilah }}</td>
                                            <td class="ds-body-sm">{{ $arti }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="ds-card-outline mt-6" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Menemukan istilah lain yang membingungkan? Tambahkan ke tabel ini —
                                glosarium hidup dari kontribusi tiap programmer baru.
                            </span>
                        </div>
                    </section>