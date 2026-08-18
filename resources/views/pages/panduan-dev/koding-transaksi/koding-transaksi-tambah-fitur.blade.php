                    {{-- ====== 09 ALUR TAMBAH FITUR ====== --}}
                    <section x-show="section === 'tambah-fitur'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Adopsi</div>
                        <h1 class="ds-display-md mb-4">Alur: Tambah Fitur</h1>
                        <p class="ds-body-md mb-8" style="max-width:62ch">
                            Pekerjaan paling sering di modul transaksi <strong>bukan membuat jalur baru</strong>,
                            melainkan menambah fitur di jalur yang sudah ada. Tiga skenario paling umum
                            di bawah — prinsipnya sama dengan modul master: <strong>salin acuan terdekat,
                            jangan menulis dari nol</strong>. Contoh path memakai satu jalur;
                            sesuaikan untuk jalur lain (ingat: RJ / UGD / RI tidak identik).
                        </p>

                        @php
                            $fiturCircle = 'display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:9999px;background:var(--primary);color:#fff;font-weight:700;font-size:13px;flex:none';
                            $fiturSkenario = [
                                [
                                    'judul' => 'A · Section EMR baru (contoh: RJ)',
                                    'acuan' => 'pages/transaksi/rj/emr-rj/anamnesa/',
                                    'steps' => [
                                        'Buat folder section + satu file actions: <span class="ds-code">emr-rj/&lt;section&gt;/rm-&lt;section&gt;-rj-actions.blade.php</span> — salin section acuan yang paling mirip; kerangka utuhnya (open → default → save → markup) ada di Bab 06. Section = child Livewire mandiri yang menerima <span class="ds-code">:rjNo</span>.',
                                        'Sepakati <strong>key JSON</strong> section di CLOB — bukan kolom baru. Simpan lewat trait jalur: <span class="ds-code">lockRJRow → findDataRJ → array_replace → updateJsonRJ</span> di dalam <span class="ds-code">DB::transaction</span> (Bab 03).',
                                        'Mount di host <span class="ds-code">emr-rj.blade.php</span> dengan <span class="ds-code">:rjNo</span> + <span class="ds-code">wire:key</span>, lalu daftarkan event <span class="ds-code">save-rm-&lt;section&gt;-rj</span> ke daftar <span class="ds-code">save-events</span> supaya ikut tombol Simpan Semua — save menerima flag <span class="ds-code">silent</span> (Bab 06).',
                                        'Tutup save() dengan helper <span class="ds-code">afterSave()</span>: incrementVersion area modal + dispatch <span class="ds-code">refresh-after-rj.saved</span> + toast (hormati flag silent). Halaman list mendengarkan event itu untuk me-refresh status &amp; persen kelengkapan — tanpa ini, data tersimpan tapi layar basi (Bab 06).',
                                        'Hormati <span class="ds-code">isFormLocked</span> (read-only penuh) dan pakai <span class="ds-code">wire:model.blur</span> untuk input numerik. Method jangan senama dengan trait EMR lain — helper lintas section = class statis.',
                                        'Bila section masuk hitungan kelengkapan EMR → tambah bobotnya di <span class="ds-code">EmrCompletenessRJTrait</span>; bila datanya tampil di display / cetakan lain (resume medis dsb.) → update konsumennya sekalian.',
                                        '<strong>Uji</strong>: buka EMR pasien uji → isi &amp; Simpan (toast muncul) → tombol Simpan Semua (satu toast gabungan, bukan beruntun) → list ter-refresh (status / persen kelengkapan berubah) → buka kunjungan LAMA yang JSON-nya belum punya key section — tidak boleh error.',
                                    ],
                                ],
                                [
                                    'judul' => 'B · Form Modul Dokumen baru (contoh: RI)',
                                    'acuan' => 'pages/transaksi/ri/emr-ri/modul-dokumen/penundaan-pelayanan-ri/ (template pola terbaru)',
                                    'steps' => [
                                        'Salin folder form acuan → <span class="ds-code">modul-dokumen/&lt;form&gt;-ri/rm-&lt;form&gt;-ri-actions.blade.php</span>. Siklus Draft → TTD → terkunci → Lihat sudah terbawa dari template; tinggal ganti field &amp; label.',
                                        'Buat blade cetak: <span class="ds-code">pages/components/modul-dokumen/ri/&lt;form&gt;-ri/cetak-&lt;form&gt;-ri-print.blade.php</span> — header identitas pasien standar (komponen x-pdf.identitas-pasien) + pola TTD cetak standar.',
                                        'Buat viewer Lihat: <span class="ds-code">pages/components/rekam-medis/ri/dokumen-view/&lt;form&gt;-view-ri.blade.php</span> — iframe yang merender blade cetak (docs/dokumen-view-pattern.md).',
                                        'Registrasi di <strong>dua tempat</strong> pada host <span class="ds-code">modul-dokumen-ri.blade.php</span>: tab / tombol pembuka + embed komponen actions dengan <span class="ds-code">wire:key</span> per <span class="ds-code">riHdrNo</span>.',
                                        'Teks klausul legal <strong>wajib versioning</strong> (<span class="ds-code">App\Support\*Clause</span> — baca <span class="ds-code">docs/clause-versioning.md</span> dulu), dan nilai pre-fill di-sync ulang di save()/finalize supaya tidak kosong di cetak (Bab 07).',
                                        '<strong>Uji</strong>: buat entri → Simpan Draft → Edit lagi (harus entri yang SAMA, bukan duplikat) → TTD &amp; Kunci → coba edit lagi (harus tertolak) → Lihat &amp; cetak: identitas pasien, TTD, dan teks klausul tampil benar.',
                                    ],
                                ],
                                [
                                    'judul' => 'C · Pos administrasi baru (contoh: RJ)',
                                    'acuan' => 'pages/transaksi/rj/administrasi-rj/lain-lain-rj.blade.php',
                                    'steps' => [
                                        'Buat file pos: <span class="ds-code">administrasi-rj/&lt;pos&gt;-rj.blade.php</span> — satu pos = satu file partial; salin pos acuan yang paling mirip.',
                                        'Include pos di host <span class="ds-code">administrasi-rj.blade.php</span> dan tambahkan <span class="ds-code">sum&lt;Pos&gt;</span> ke <span class="ds-code">sumAll()</span> — kalau lupa, grand total &amp; tagihan kasir salah diam-diam (Bab 08).',
                                        'Mutasi finansial selalu <span class="ds-code">DB::transaction</span> + <span class="ds-code">lockForUpdate</span>; nominal di UI pakai <span class="ds-code">x-text-input-number</span>.',
                                        'Catat aksi admin lewat <span class="ds-code">appendAdminLogRJ()</span> (muncul di tab Log Aktivitas); aksi sensitif — hapus / ubah tarif / posting — di-guard role.',
                                        '<strong>Uji</strong>: tambah item pos → grand total &amp; breakdown berubah → Selesai Administrasi → pasien muncul di antrian kasir → posting bayar (rj_status jadi L; coba tambah item — harus tertolak) → batal posting → kembali A dan bisa diubah lagi.',
                                    ],
                                ],
                            ];
                        @endphp

                        @foreach ($fiturSkenario as $sk)
                            <h2 class="ds-title-lg {{ $loop->first ? '' : 'mt-10' }} mb-1">{{ $sk['judul'] }}</h2>
                            <p class="ds-caption mb-4" style="color:var(--muted)">Acuan / template: <span class="ds-code">{{ $sk['acuan'] }}</span></p>
                            <div>
                                @foreach ($sk['steps'] as $step)
                                    <div class="flex gap-4">
                                        <div class="flex flex-col items-center">
                                            <span style="{{ $fiturCircle }}">{{ $loop->iteration }}</span>
                                            @if (! $loop->last)
                                                <span class="flex-1" style="width:2px; background:var(--hairline); margin-top:4px"></span>
                                            @endif
                                        </div>
                                        <div class="flex-1 {{ $loop->last ? '' : 'pb-5' }}" style="min-width:0">
                                            <p class="ds-body-sm" style="max-width:62ch; padding-top:5px">{!! $step !!}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if ($loop->first)
                                <h3 class="ds-title-sm mt-6 mb-2">Padanan per jalur — langkahnya sama, namanya yang beda</h3>
                                <div class="ds-card-outline" style="padding:0; overflow-x:auto">
                                    <table class="ds-table">
                                        <thead>
                                            <tr><th>Hal</th><th>RJ</th><th>UGD</th><th>RI</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="ds-td-strong">Folder section</td>
                                                <td class="ds-td-class">rj/emr-rj/&lt;section&gt;/</td>
                                                <td class="ds-td-class">ugd/emr-ugd/&lt;section&gt;/</td>
                                                <td class="ds-td-class">ri/emr-ri/&lt;section&gt;-ri/</td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Host EMR</td>
                                                <td class="ds-td-class">emr-rj.blade.php</td>
                                                <td class="ds-td-class">emr-ugd.blade.php</td>
                                                <td class="ds-td-class">emr-ri.blade.php <span class="ds-body-sm">(section terdaftar di array key/label/saveEvent)</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Prop kunci</td>
                                                <td class="ds-td-class">:rjNo (rstxn_rjhdrs)</td>
                                                <td class="ds-td-class">:rjNo (rstxn_ugdhdrs)</td>
                                                <td class="ds-td-class">:riHdrNo (rstxn_rihdrs)</td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Trait &amp; method</td>
                                                <td class="ds-td-class">EmrRJTrait<br>lockRJRow · findDataRJ · updateJsonRJ</td>
                                                <td class="ds-td-class">EmrUGDTrait<br>lockUGDRow · findDataUGD · updateJsonUGD</td>
                                                <td class="ds-td-class">EmrRITrait<br>lockRIRow · findDataRI · updateJsonRI</td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Event save</td>
                                                <td class="ds-td-class">save-rm-&lt;section&gt;-rj</td>
                                                <td class="ds-td-class">save-rm-&lt;section&gt;-ugd</td>
                                                <td class="ds-td-class">save-rm-&lt;section&gt;-ri <span class="ds-body-sm">(multi-record aktif: save-active-rm-*-ri)</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Refresh after-save</td>
                                                <td class="ds-td-class">refresh-after-rj.saved<br><span class="ds-body-sm">→ pelayanan-rj</span></td>
                                                <td class="ds-td-class">refresh-after-ugd.saved<br><span class="ds-body-sm">→ pelayanan-ugd</span></td>
                                                <td class="ds-td-class">refresh-after-ri.saved<br><span class="ds-body-sm">→ daftar-ri + display-pasien-ri</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Kelengkapan</td>
                                                <td class="ds-td-class">EmrCompletenessRJTrait<br><span class="ds-body-sm">S15 / O20 / A25 / P20 / N10 / K10</span></td>
                                                <td class="ds-td-class">EmrCompletenessUGDTrait<br><span class="ds-body-sm">S15 / O15 / A20 / P15 / N10 / T15 / K10</span></td>
                                                <td class="ds-td-class">EmrCompletenessRITrait <span class="ds-body-sm">(bobot beda: + CPPT &amp; keperawatan)</span></td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">EMR dibuka dari</td>
                                                <td class="ds-body-sm">Pelayanan RJ</td>
                                                <td class="ds-body-sm">Pelayanan UGD</td>
                                                <td class="ds-body-sm">langsung dari Daftar RI (RI tanpa halaman pelayanan)</td>
                                            </tr>
                                            <tr>
                                                <td class="ds-td-strong">Section khas jalur</td>
                                                <td class="ds-body-sm">Screening · SKDP · PRB</td>
                                                <td class="ds-body-sm">Triase P0–P3 (anamnesa) · Obat &amp; Cairan · Observasi · Rujukan antar RS</td>
                                                <td class="ds-body-sm">Pengkajian Awal / Dokter · CPPT · SBAR · Asuhan Keperawatan (multi-entry)</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="ds-caption mt-2 mb-2" style="color:var(--muted)">
                                    Awas dua jebakan: UGD juga memakai nama kolom <span class="ds-code">rj_no</span>
                                    (tapi tabelnya <span class="ds-code">rstxn_ugdhdrs</span>, bukan rjhdrs) — jangan tertukar;
                                    dan di RI seluruh folder/file/event <strong>bersuffix -ri</strong> serta section
                                    baru harus didaftarkan ke array section di host <span class="ds-code">emr-ri</span>.
                                </p>
                            @endif
                        @endforeach

                        <div class="ds-card-outline mt-10" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Selesai menambah fitur? Jalankan
                                <button type="button" class="hover:underline font-semibold" style="color:var(--primary)"
                                    x-on:click="go('adopsi')">Checklist Adopsi</button>
                                — plus checklist Tutorial Koding Master untuk urusan komponen, validasi, dan LOV.
                            </span>
                        </div>
                    </section>