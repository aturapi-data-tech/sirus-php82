                    {{-- ====== 06 EMR ====== --}}
                    <section x-show="section === 'emr'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">EMR (Rekam Medis)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            EMR = <strong>modal full-screen</strong> (bukan route) yang di-embed sebagai sibling
                            di halaman pelayanan. Layout-nya mengikuti <strong>SOAP</strong>:
                            tiap huruf = satu/dua <em>section</em>, dan tiap section =
                            <strong>child Livewire mandiri</strong> yang menerima <span class="ds-code">:rjNo</span>.
                        </p>

                        {{-- visual SOAP grid --}}
                        <div class="ds-frame mt-2 mb-6">
                            <div class="ds-frame-label">Tata letak host EMR (emr-rj)</div>
                            <div class="grid grid-cols-2 gap-2 mt-3">
                                @foreach ([
                                    ['S', 'Subjective — Anamnesa', 'var(--info)'],
                                    ['O', 'Objective — Pemeriksaan', 'var(--success)'],
                                    ['A', 'Assessment — Diagnosa + Penilaian', 'var(--warning)'],
                                    ['P', 'Plan — Perencanaan', 'var(--error)'],
                                ] as [$huruf, $nama, $warna])
                                    <div class="ds-card-outline" style="padding:14px">
                                        <span class="inline-flex items-center justify-center w-8 h-8 mr-2 text-base font-bold rounded-full"
                                            style="background:color-mix(in srgb, {{ $warna }} 15%, transparent); color:{{ $warna }}">{{ $huruf }}</span>
                                        <span class="text-sm font-semibold" style="color:var(--ink)">{{ $nama }}</span>
                                        <p class="ds-caption mt-2" style="color:var(--muted)">child livewire · :rjNo · wire:key per rjNo</p>
                                    </div>
                                @endforeach
                            </div>
                            <p class="ds-caption mt-3" style="color:var(--muted)">
                                Header modal = <span class="ds-code">display-pasien-rj</span> (kartu identitas).
                                Screening, Modul Dokumen, Administrasi, E-Resep, Log Aktivitas = tombol yang membuka MODAL LAIN via event.
                                Cara MEMBUAT satu section dari nol: lihat kartu "Membuat section — kerangka utuh" di bawah.
                            </p>
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Host — buka EMR &amp; sebarkan event open</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-host'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Section mounting + save-all broadcast</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-section'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Membuat section — kerangka utuh rm-&lt;section&gt;-rj-actions.blade.php</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-section-skeleton'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Di dalam section — save dgn flag silent</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-save'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Setelah save — afterSave() &amp; refresh list (refresh-after-&lt;jalur&gt;.saved)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-after-save'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">E-Resep dokter — modal sibling, tab Racikan / Non-Racikan, dual-write</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-eresep'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Order penunjang dari section Pemeriksaan — Lab, Radiologi &amp; Kamar Operasi</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['emr-penunjang'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Aturan section EMR</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>Satu section = satu folder + satu file actions; state tidak bocor antar section</li>
                                    <li>Jangan ada method senama antar trait EMR di satu kelas (tabrakan trait) — helper lintas section = class statis (<span class="ds-code">App\Support\LogText</span>)</li>
                                    <li>Input numerik pakai <span class="ds-code">wire:model.blur</span> (bukan .live) — digit hilang saat race</li>
                                    <li><span class="ds-code">isFormLocked</span> dihormati SEMUA section (read-only penuh)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Kelengkapan EMR</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><span class="ds-code">EmrCompletenessRJTrait::calculateEmrPercentRJ()</span> — bobot S15/O20/A25/P20/N10/K10 (K = koding SNOMED)</li>
                                    <li>Ditampilkan sebagai progress di list (info-kelengkapan-emr)</li>
                                    <li>RI bobotnya beda (+CPPT &amp; keperawatan) — jangan samakan lintas jalur</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    {{-- ====== 07 MODUL DOKUMEN ====== --}}
                    <section x-show="section === 'dokumen'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Tahapan</div>
                        <h1 class="ds-display-md mb-4">Modul Dokumen</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Form dokumen resmi bertanda tangan (consent, surat keterangan, laporan operasi…).
                            RJ punya 4 form; RI ±28 form (obstetri, bedah, anestesi). Satu form = pola
                            <strong>kartu + tombol Buka → modal</strong> dengan siklus hidup
                            <strong>Draft → TTD → terkunci → Lihat</strong>.
                        </p>

                        {{-- visual siklus --}}
                        <div class="flex flex-wrap items-center gap-2 mb-6">
                            @foreach ([['Draft', 'simpan sebagian, bebas edit'], ['TTD petugas/pasien', 'validasi lengkap + stempel'], ['Terkunci', 'isFormLocked — read only'], ['Lihat / Cetak', 'viewer iframe render blade cetak']] as $i => [$fase, $ket])
                                @if ($i > 0)<span class="ds-code" style="color:var(--primary)">▶</span>@endif
                                <span class="ds-card-outline" style="padding:8px 14px; {{ $i === 2 ? 'border-color:var(--warning)' : '' }}">
                                    <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $fase }}</span>
                                    <span class="block text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                </span>
                            @endforeach
                        </div>

                        {{-- TIGA VARIAN PENYIMPANAN — paling sering salah pilih di awal --}}
                        <div class="ds-frame mt-2 mb-6">
                            <div class="ds-frame-label">Di mana datanya disimpan? Tiga varian</div>
                            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                                @foreach ([
                                    ['Menumpang JSON kunjungan', 'datadaftar*_json milik SATU kunjungan. Mayoritas form dokumen ada di sini.', 'consent, suket, laporan operasi', 'modul-dokumen'],
                                    ['Multi-entri dalam JSON', 'Banyak entri per pasien di JSON kunjungan, tab per-profesi, review DPJP.', 'CPPT, SBAR', 'emr-multi-entry-document'],
                                    ['Tabel sendiri per kunjungan', 'Tabel 3 kolom: PK, REG_NO, CLOB JSON. SATU BARIS PER KUNJUNGAN; formulir multi-baris dirakit saat tampil/cetak.', 'Pengkajian Medis PP 1.2, PRMRJ RM.06', 'dokumen-clob-per-kunjungan'],
                                ] as $i => [$judul, $ket, $contoh, $skill])
                                    <div class="ds-card-outline" style="padding:12px 14px; {{ $i === 2 ? 'border-color:var(--primary)' : '' }}">
                                        <span class="block text-sm font-semibold" style="color:var(--ink)">{{ $judul }}</span>
                                        <span class="block mt-1 text-xs" style="color:var(--muted)">{{ $ket }}</span>
                                        <span class="block mt-2 text-xs" style="color:var(--muted-soft)">Contoh: {{ $contoh }}</span>
                                        <span class="ds-code mt-2" style="display:inline-block">/{{ $skill }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <p class="ds-body-sm mt-3" style="max-width:70ch; color:var(--muted)">
                                Varian ketiga dipakai saat isi dokumen menyangkut <strong>banyak kunjungan</strong>
                                atau perlu dicari lintas pasien &mdash; JSON satu kunjungan tak cukup.
                                Tetap <strong>satu baris per kunjungan</strong>, bukan per pasien: kalau satu pasien
                                satu CLOB, dua poli yang membuka pasien sama harus saling menunggu lock dan satu
                                kesalahan tulis merusak seluruh riwayat pasien. Hanya <span class="ds-code">REG_NO</span>
                                yang jadi kolom datar &mdash; Oracle di sini tak mendukung
                                <span class="ds-code">JSON_VALUE</span>, jadi isi CLOB tak bisa dipakai memfilter
                                maupun mengurutkan.
                            </p>
                        </div>

                        @php
                            $dokBadge = 'display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;border-radius:9999px;background:var(--primary);color:#fff;font-size:11px;font-weight:700;line-height:1;flex:none';
                        @endphp

                        {{-- visual anatomi form dokumen multi-entri --}}
                        <div class="ds-frame mt-2 mb-2">
                            <div class="ds-frame-label">Tata letak form dokumen multi-entri (modal)</div>
                            <div class="mt-3" style="border:1px solid var(--hairline); border-radius:14px; overflow:hidden; background:var(--canvas)">

                                {{-- header --}}
                                <div class="flex items-center justify-between gap-3 px-4 py-3" style="background:var(--surface-soft); border-bottom:1px solid var(--hairline)">
                                    <span class="flex items-center gap-2">
                                        <span class="ds-title-sm">Penundaan Pelayanan</span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full" style="background:var(--info-tint); color:var(--info-deep)">2 entri</span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <span style="color:var(--muted)">✕</span>
                                        <span style="{{ $dokBadge }}">1</span>
                                    </span>
                                </div>

                                {{-- tabel entri multi-record --}}
                                <div class="px-4 py-3" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    <div class="flex flex-wrap items-center gap-2 py-1.5" style="border-bottom:1px solid var(--hairline-soft)">
                                        <span class="ds-body-sm" style="font-family:var(--mono)">08/07/2026 10:12</span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full" style="background:var(--success-tint); color:var(--success-deep)">Terkunci</span>
                                        <span class="ds-caption" style="color:var(--muted)">aksi: Lihat</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 py-1.5">
                                        <span class="ds-body-sm" style="font-family:var(--mono)">10/07/2026 08:45</span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full" style="background:var(--warning-tint); color:var(--warning-deep)">Draft</span>
                                        <span class="ds-caption" style="color:var(--muted)">aksi: Edit · TTD &amp; Kunci</span>
                                    </div>
                                    <span style="{{ $dokBadge }};position:absolute;top:10px;right:12px;background:var(--info)">2</span>
                                </div>

                                {{-- form entri aktif --}}
                                <div class="px-4 py-3" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    <span class="block mb-1 text-xs font-medium" style="color:var(--body)">Alasan penundaan</span>
                                    <div class="flex items-center gap-2">
                                        <div style="height:34px;padding:8px 12px;border-radius:8px;border:1px solid var(--hairline);background:var(--canvas);color:var(--muted-soft);font-size:13px;flex:1;display:flex;align-items:center">Menunggu hasil laboratorium...</div>
                                        <span class="px-2 py-1.5 text-xs rounded-lg" style="border:1px solid var(--hairline); color:var(--muted)" title="x-now-button">🕐</span>
                                    </div>
                                    <span style="{{ $dokBadge }};position:absolute;top:10px;right:12px">3</span>
                                </div>

                                {{-- area TTD --}}
                                <div class="grid grid-cols-1 gap-3 px-4 py-3 sm:grid-cols-2" style="position:relative; border-bottom:1px solid var(--hairline)">
                                    <div class="p-3 text-center" style="border:1px dashed var(--hairline); border-radius:10px">
                                        <span class="ds-caption" style="color:var(--muted)">TTD Pasien / Keluarga</span>
                                        <div class="mt-4 mb-1 mx-8" style="border-bottom:1px solid var(--muted-soft)"></div>
                                        <span class="ds-caption" style="color:var(--muted-soft)">signature-pad (dataURL) — bisa menyusul</span>
                                    </div>
                                    <div class="p-3 text-center" style="border:1px dashed var(--hairline); border-radius:10px">
                                        <span class="ds-caption" style="color:var(--muted)">TTD Petugas</span>
                                        <div class="mt-3 mb-1 text-sm font-semibold" style="color:var(--ink)">Ns. FULAN, S.Kep</div>
                                        <span class="ds-caption" style="color:var(--muted-soft)">komponen ttd-petugas — klik = stempel nama + kode</span>
                                    </div>
                                    <span style="{{ $dokBadge }};position:absolute;top:10px;right:12px">4</span>
                                </div>

                                {{-- footer --}}
                                <div class="flex items-center justify-end gap-2 px-4 py-3" style="background:var(--surface-soft)">
                                    <span class="ds-btn ds-btn-secondary" style="height:32px; padding:6px 12px; font-size:12px">Simpan Draft</span>
                                    <span class="ds-btn ds-btn-primary" style="height:32px; padding:6px 12px; font-size:12px">TTD &amp; Kunci</span>
                                    <span style="{{ $dokBadge }}">5</span>
                                </div>
                            </div>
                        </div>

                        {{-- legenda anatomi dokumen --}}
                        <div class="grid grid-cols-1 gap-2 mb-6 sm:grid-cols-2">
                            @foreach ([
                                ['1', 'Header form + jumlah entri — dibuka dari host: RI = tab per form (modul-dokumen-ri), RJ = kartu + tombol Buka', ''],
                                ['2', 'Tabel entri multi-record — Draft (kuning: bisa Edit) vs Terkunci (hijau: hanya Lihat = viewer iframe render blade cetak); kunci stabil entri = signatureDate', 'background:var(--info)'],
                                ['3', 'Form entri aktif — semua input di-guard isFormLocked / viewOnly; tombol jam = x-now-button', ''],
                                ['4', 'TTD pasien = signature-pad (bisa "TTD menyusul"/staged) · TTD petugas = komponen ttd-petugas (stempel nama + ttdCode, guard server-side)', ''],
                                ['5', 'Simpan Draft (validasi minimal) vs TTD & Kunci (validasi lengkap → entri terkunci permanen)', ''],
                            ] as [$num, $ket, $extra])
                                <div class="flex items-start gap-2.5">
                                    <span style="{{ $dokBadge }}; margin-top:2px; {{ $extra }}">{{ $num }}</span>
                                    <span class="ds-body-sm">{{ $ket }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="ds-card-dark mt-2" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Dua tahap: draft vs finalize — rm-general-consent-rj-actions</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-flow'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Membuat form — kerangka utuh multi-entri (penundaan-pelayanan-ri)</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-skeleton'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Clause versioning — registry per-versi (GeneralConsentClause) + snapshot</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-clause'] }}</pre>
                        </div>

                        <div class="ds-card-dark mt-4" style="padding:0; overflow:hidden">
                            <div class="px-4 py-2.5" style="background:var(--surface-dark-soft)">
                                <span class="ds-caption-up" style="color:var(--on-dark-soft)">Pola cetak / PDF — header identitas, TTD, jebakan Tailwind arbitrary</span>
                            </div>
                            <pre class="ds-code" style="margin:0; padding:20px 24px; color:var(--on-dark-soft); overflow-x:auto; line-height:1.7">{{ $snip['dokumen-cetak'] }}</pre>
                        </div>

                        <div class="grid grid-cols-1 gap-4 mt-8 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Komponen &amp; pola pendukung</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li>TTD petugas di layar: <span class="ds-code">x-signature.ttd-petugas</span> (guard server-side + simpan ttdCode)</li>
                                    <li>TTD pasien: signature-pad (dataURL) — bisa "TTD menyusul" (staged)</li>
                                    <li>Multi-entri (form berulang per kunjungan): tabel record expandable + Draft/Edit/TTD-kunci/Lihat</li>
                                    <li>Lihat = viewer iframe merender blade cetak (docs/dokumen-view-pattern.md)</li>
                                </ul>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Dua aturan keras</div>
                                <ul class="ds-body-sm space-y-1.5" style="list-style:disc; padding-left:18px">
                                    <li><strong>Teks klausul = versioning</strong> (<span class="ds-code">App\Support\*Clause</span>) — cetak ulang record lama WAJIB memakai redaksi saat ditandatangani. Baca <span class="ds-code">docs/clause-versioning.md</span> sebelum mengubah teks apa pun</li>
                                    <li><strong>Pre-fill wajib di-sync di save()</strong> — nilai prop yang tidak diedit user tidak otomatis masuk array form (hilang di cetak)</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Tanda tangan & buka kunci (baku sejak Inform Consent / Akhir Hayat) --}}
                        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Tanda tangan: 3 pihak, petugas TERAKHIR</div>
                                <div class="ds-body-sm">
                                    Pasien/keluarga (wajib) &middot; saksi (opsional, tampil langsung) &middot; petugas.
                                    <strong>TTD petugas = aksi terakhir yang sekaligus MENGUNCI</strong> entri
                                    (<span class="ds-code">setDokterPenjelas</span> / <span class="ds-code">ttdPetugas</span>) —
                                    JANGAN bikin tombol &ldquo;Simpan &amp; Kunci&rdquo; terpisah; footer cukup Simpan Draft.
                                    TTD masuk <span class="ds-code">rules()</span> supaya errornya merah di kolomnya, bukan cek manual.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Buka kunci (unlock)</div>
                                <div class="ds-body-sm">
                                    Hanya <span class="ds-code">Admin | Manager Umum | Manager Medis</span>, gate DUA lapis
                                    (<span class="ds-code">&#64;hasanyrole</span> di tombol + cek role di server). Mencabut
                                    <span class="ds-code">finalized</span> + <strong>TTD petugas saja</strong>; TTD pasien &amp;
                                    saksi DIPERTAHANKAN. Wajib <span class="ds-code">appendAdminLogRI(&hellip;, 'MR')</span>
                                    yang menyebut pelakunya.
                                </div>
                            </div>
                        </div>

                        {{-- Port lintas jalur + viewer rekam medis (baku sejak Akhir Hayat RI→UGD) --}}
                        <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-2">
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Port ke jalur lain (RI ⇄ UGD ⇄ RJ)</div>
                                <div class="ds-body-sm">
                                    Satu form sering dipasang di beberapa jalur — <strong>salin</strong> actions + cetak,
                                    ganti token <strong>per-string</strong> (bukan <span class="ds-code">RI→UGD</span> global):
                                    <span class="ds-code">EmrRITrait→EmrUGDTrait</span>,
                                    <span class="ds-code">?string $riHdrNo→?int $rjNo</span>,
                                    <span class="ds-code">findDataRI/updateJsonRI/lockRIRow/appendAdminLogRI→…UGD</span>,
                                    key JSON <span class="ds-code">pengkajian&lt;Dok&gt;RI→…UGD</span>.
                                    Folder/file UGD/RJ <strong>buang sufiks</strong> <span class="ds-code">-ri</span>,
                                    tapi modal-name / renderArea / nama PDF <strong>tetap</strong> <span class="ds-code">-ugd</span>.
                                    <span class="ds-code">*Clause</span> &amp; <span class="ds-code">*Options</span> dipakai bersama — jangan diduplikasi.
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:20px">
                                <div class="ds-title-sm mb-2">Viewer rekam medis — WAJIB</div>
                                <div class="ds-body-sm">
                                    Dokumen baru belum selesai sampai bisa dilihat di <strong>display Rekam Medis</strong>:
                                    buat <span class="ds-code">rekam-medis/&lt;jalur&gt;/dokumen-view/&lt;dok&gt;-view-&lt;jalur&gt;</span>
                                    lalu daftarkan di <span class="ds-code">cetak-rekam-medis-open</span>.
                                    Cetak payload seragam → pakai <span class="ds-code">DokumenViewSupportTrait</span> langsung;
                                    payload bespoke (butuh <span class="ds-code">entry+opsiLabel+clause</span>, mis. Akhir Hayat) →
                                    viewer self-contained dgn <span class="ds-code">buildData()</span> peniru <span class="ds-code">cetak()</span>.
                                </div>
                            </div>
                        </div>

                        <div class="ds-card-outline mt-4" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Rincian pola (struktur file, siklus entri, rancangan panel &amp; opsi, jebakan Blade
                                escape-ganda <span class="ds-code">&amp;amp;</span> pada prop komponen, <strong>§9 port jalur</strong>,
                                <strong>§10 viewer rekam-medis</strong>) ada di
                                <span class="ds-code">docs/modul-dokumen-ri-pattern.md</span>. Verifikasi final =
                                <span class="ds-code">php artisan view:cache</span> (EXIT 0), bukan compileString.
                            </span>
                        </div>
                    </section>