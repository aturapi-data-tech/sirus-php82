<?php

use Livewire\Component;

// Tutorial RUJUKAN BERBASIS KOMPETENSI (SRBK) — alur, arsitektur dua jalur
// (BPJS vclaim-sisrute utk poli RJ vs FHIR langsung SATUSEHAT utk IGD/Ranap),
// lokasi panel di SIMRS, dan FAQ permasalahan hasil studi 13.6k baris chat
// grup resmi BPJS x Kemkes (Apr-Agu 2026). Gaya sidebar sama dgn alur-pelayanan.
// Sumber detail teknis: docs/rujukan-kompetensi.md + skill rujukan-kompetensi.
new class extends Component {
    //
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap"
        rel="stylesheet" />

    @php
        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'arsitektur' => 'Arsitektur Dua Jalur',
                'prasyarat' => 'Prasyarat & Kredensial',
            ],
            'Alur' => [
                'alur-rj' => 'Jalur RJ → Poli RS Lain',
                'alur-fhir' => 'Jalur FHIR — IGD & Ranap',
                'alur-cross' => 'Lintas Jalur & Internal RS',
            ],
            'Payload & API' => [
                'api-env' => 'URL API & Env',
                'json-rj' => 'Contoh JSON — Jalur RJ',
                'json-fhir' => 'Contoh JSON — Jalur FHIR',
            ],
            'Di SIMRS' => [
                'simrs-alur' => 'Alur Program & Diagram',
                'simrs-panel' => 'Lokasi Panel & Node Data',
            ],
            'FAQ' => [
                'faq-error' => 'Katalog Error → Penanganan',
                'faq-umum' => 'Pertanyaan Umum',
            ],
            'Referensi' => [
                'referensi' => 'Dokumen & Sumber',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('panduan-dev') }}" wire:navigate class="ds-body-sm hover:underline"
                        style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Rujukan Kompetensi</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('panduan-dev.koding-satusehat') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">←
                        Koding SATUSEHAT</a>
                    <x-theme-toggle />
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Detail teknis: <span class="ds-code">docs/rujukan-kompetensi.md</span><br>
                            Skill repo: <span class="ds-code">rujukan-kompetensi</span><br>
                            Sumber: chat grup resmi BPJS×Kemkes Apr–Agu 2026.
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    {{-- ====== PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Rujukan Berbasis Kompetensi (SRBK)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            SRBK menggantikan rujukan "tunjuk RS sendiri" dengan alur ber-<strong>kandidat</strong>:
                            sistem pusat menilai <strong>diagnosa + kriteria</strong> (terapi / tindakan ICD-9 /
                            upaya diagnosis) lalu mengembalikan <strong>daftar RS yang kompeten</strong> menangani
                            pasien — lengkap dengan jarak, waktu tempuh, strata, dan (untuk ranap) ketersediaan
                            tempat tidur. Rumah sakit perujuk tinggal memilih dari kandidat itu.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Prinsip nomor satu</div>
                            <p class="ds-body-md" style="max-width:62ch">
                                <strong>Jalur rujukan ditentukan oleh layanan yang dibutuhkan pasien di RS
                                TUJUAN</strong> — bukan dari unit mana pasien sekarang berada. Pasien poli yang
                                butuh IGD RS lain memakai jalur darurat; pasien UGD yang butuh ranap RS lain
                                memakai jalur ranap.
                            </p>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Matriks jalur (asal → tujuan RS lain)</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Dari \ Tujuan</th><th>Poli RJ</th><th>IGD</th><th>Rawat Inap</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">RJ (poli)</td><td class="ds-body-sm">BPJS vclaim-sisrute</td><td class="ds-body-sm">FHIR langsung</td><td class="ds-body-sm">FHIR langsung</td></tr>
                                        <tr><td class="ds-td-strong">UGD</td><td class="ds-body-sm">—</td><td class="ds-body-sm">FHIR langsung</td><td class="ds-body-sm">FHIR langsung</td></tr>
                                        <tr><td class="ds-td-strong">RI</td><td class="ds-body-sm">—</td><td class="ds-body-sm">—</td><td class="ds-body-sm">FHIR langsung</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted)">
                            Perpindahan pasien <strong>di dalam RS sendiri</strong> (RJ→UGD, UGD→RI) BUKAN objek
                            SRBK — tetap alur internal biasa (SEP, transfer, Encounter baru). Lihat seksi
                            "Lintas Jalur &amp; Internal RS".
                        </p>
                    </section>

                    {{-- ====== ARSITEKTUR ====== --}}
                    <section x-show="section === 'arsitektur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Arsitektur Dua Jalur</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Diagram</div>
                            <div class="overflow-x-auto">
                                <svg viewBox="0 0 860 300" style="min-width:700px; width:100%; font-family:inherit">
                                    <defs>
                                        <marker id="panah-srbk" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7"
                                            markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--muted)" />
                                        </marker>
                                    </defs>
                                    <rect x="20" y="110" width="160" height="70" rx="12" fill="var(--surface-card)" />
                                    <text x="100" y="140" text-anchor="middle" font-size="14" font-weight="600" fill="var(--ink)">SIMRS</text>
                                    <text x="100" y="160" text-anchor="middle" font-size="11" fill="var(--muted)">EMR RJ / UGD / RI</text>

                                    <rect x="330" y="30" width="220" height="70" rx="12" fill="var(--surface-card)" />
                                    <text x="440" y="58" text-anchor="middle" font-size="13" font-weight="600" fill="var(--ink)">BPJS vclaim-sisrute-rest</text>
                                    <text x="440" y="78" text-anchor="middle" font-size="11" fill="var(--muted)">khusus tujuan POLI rawat jalan</text>

                                    <rect x="330" y="190" width="220" height="70" rx="12" fill="var(--surface-card)" />
                                    <text x="440" y="218" text-anchor="middle" font-size="13" font-weight="600" fill="var(--ink)">SATUSEHAT FHIR R4</text>
                                    <text x="440" y="238" text-anchor="middle" font-size="11" fill="var(--muted)">tujuan IGD &amp; Rawat Inap</text>

                                    <rect x="690" y="110" width="150" height="70" rx="12" fill="var(--surface-card)" />
                                    <text x="765" y="140" text-anchor="middle" font-size="14" font-weight="600" fill="var(--ink)">SATUSEHAT</text>
                                    <text x="765" y="160" text-anchor="middle" font-size="11" fill="var(--muted)">Rujukan Nasional</text>

                                    <line x1="180" y1="128" x2="324" y2="68" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-srbk)" />
                                    <line x1="180" y1="162" x2="324" y2="222" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-srbk)" />
                                    <line x1="550" y1="65" x2="700" y2="118" stroke="var(--muted)" stroke-width="2" stroke-dasharray="6 4" marker-end="url(#panah-srbk)" />
                                    <line x1="550" y1="225" x2="700" y2="172" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-srbk)" />
                                    <text x="628" y="78" text-anchor="middle" font-size="11" fill="var(--muted)">BPJS yang meneruskan</text>
                                    <text x="628" y="216" text-anchor="middle" font-size="11" fill="var(--muted)">kirim sendiri (bundle)</text>
                                </svg>
                            </div>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Perbandingan</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Hal</th><th>Jalur BPJS (RJ→poli)</th><th>Jalur FHIR (IGD/Ranap)</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Siapa kirim ke SATUSEHAT</td><td class="ds-body-sm"><strong>BPJS</strong> — kita TIDAK kirim bundle FHIR</td><td class="ds-body-sm"><strong>Kita</strong> — tanpa rujukan VClaim sama sekali</td></tr>
                                        <tr><td class="ds-td-strong">Trait</td><td class="ds-body-sm"><span class="ds-code">SisruteTrait</span> (pola VclaimTrait, HMAC)</td><td class="ds-body-sm"><span class="ds-code">SatuSehatRujukanTrait</span> (OAuth2 token khusus)</td></tr>
                                        <tr><td class="ds-td-strong">Endpoint</td><td class="ds-body-sm">GetKriteriaRujukan (<strong>POST!</strong>) → GetFaskesRujukan → Rujukan/Insert → Delete (method DELETE)</td><td class="ds-body-sm">Task pre-request → Task kandidat → Bundle Task+CarePlan → ServiceRequest</td></tr>
                                        <tr><td class="ds-td-strong">Management procedure</td><td class="ds-body-sm">— (jnsPelayanan "2")</td><td class="ds-body-sm">IGD <span class="ds-code">385868005</span> / Ranap <span class="ds-code">737481003</span></td></tr>
                                        <tr><td class="ds-td-strong">Tanda sukses</td><td class="ds-body-sm" colspan="2">Identifier <span class="ds-code">referral-number-satusehat</span> TERBIT — tanpa itu = gagal walau resource terbentuk; nomor wajib tersimpan di DB (syarat UAT)</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    {{-- ====== PRASYARAT ====== --}}
                    <section x-show="section === 'prasyarat'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Prasyarat &amp; Kredensial</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Kredensial — dua-duanya KHUSUS, bukan yang biasa</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Jalur</th><th>Kredensial</th><th>Cara dapat</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">BPJS SISRUTE</td><td class="ds-body-sm">Cons-ID jenis <strong>SISRUTE</strong> (env <span class="ds-code">SISRUTE_CONS_ID/SECRET_KEY/USER_KEY</span>). Cons-id vclaim biasa DITOLAK ("not registered for this service").</td><td class="ds-body-sm">Ajukan ke BPJS (Kantor Cabang / form pendataan). Expired → ajukan "reaktivasi".</td></tr>
                                        <tr><td class="ds-td-strong">SATUSEHAT Rujukan (staging)</td><td class="ds-body-sm">client_id/secret KHUSUS (env <span class="ds-code">SATUSEHAT_CLIENT_ID/SECRET_ID</span>) — BEDA dari dashboard platform.</td><td class="ds-body-sm">Japri tim SATUSEHAT Rujukan: email login platform + org-id production.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Prasyarat data per pasien (panel menampilkan kotak merah bila kurang)</div>
                            <ul class="ds-body-md space-y-1" style="max-width:66ch; list-style:disc; padding-left:20px">
                                <li><strong>Encounter SATUSEHAT</strong> episode berjalan sudah terkirim (menu Satu Sehat → Encounter).</li>
                                <li><strong>IHS Pasien</strong> (<span class="ds-code">rsmst_pasiens.patient_uuid</span>) dan <strong>IHS Dokter</strong> (<span class="ds-code">rsmst_doctors.dr_uuid</span>) terisi.</li>
                                <li>Jalur RJ: <strong>SEP</strong> sudah terbit; diagnosa EMR terisi.</li>
                                <li>Diagnosa <strong>ICD-10 rinci 4-karakter ber-titik</strong> (A02.0) — kode induk 3 karakter DITOLAK.</li>
                            </ul>
                        </div>
                    </section>

                    {{-- ====== ALUR RJ ====== --}}
                    <section x-show="section === 'alur-rj'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Alur</div>
                        <h1 class="ds-display-md mb-4">Jalur RJ → Poli RS Lain (BPJS vclaim-sisrute)</h1>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-4" style="color:var(--muted)">Tiga langkah di panel</div>
                            <div class="flex flex-wrap items-stretch gap-2">
                                @foreach ([
                                    ['1', 'Kriteria', 'Ambil dari server per ICD-10'],
                                    ['2', 'Kandidat', 'Cari faskes → pilih SATU'],
                                    ['3', 'Kirim', 'Insert → nomor rujukan'],
                                ] as [$no, $judul, $sub])
                                    <div class="flex-1 min-w-[150px] rounded-xl p-4" style="background:var(--surface-card)">
                                        <div class="ds-caption-up" style="color:var(--primary)">Langkah {{ $no }}</div>
                                        <div class="ds-title-md" style="color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-body-sm" style="color:var(--muted)">{{ $sub }}</div>
                                    </div>
                                    @if (!$loop->last)
                                        <div class="self-center ds-title-md" style="color:var(--muted-soft)">→</div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Aturan payload yang paling sering bikin salah</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Aturan</th><th>Detail</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Kriteria TEPAT SATU</td><td class="ds-body-sm">Terapi / Tindakan Medis / Upaya Diagnosis — validasi ketat sejak Jul 2026; lebih dari satu DITOLAK. Tindakan Medis = kode ICD-9-CM valid &amp; sesuai diagnosa (menentukan kandidat).</td></tr>
                                        <tr><td class="ds-td-strong">linkId dinamis</td><td class="ds-body-sm">linkId kriteria BERBEDA per ICD-10 — selalu ambil ulang dari GetKriteriaRujukan, jangan hardcode. Ganti diagnosa = ambil kriteria ulang (panel melakukannya otomatis).</td></tr>
                                        <tr><td class="ds-td-strong">Dua format tanggal</td><td class="ds-body-sm"><span class="ds-code">estimasiRujuk</span> = dd-mm-yyyy; <span class="ds-code">tglRujukan/tglRencanaKunjungan</span> = yyyy-mm-dd. Estimasi boleh hari ini.</td></tr>
                                        <tr><td class="ds-td-strong">Pasangan kode tujuan</td><td class="ds-body-sm"><span class="ds-code">ppkDirujuk</span> (BPJS) ↔ <span class="ds-code">kdppkSatuSehatTujuanRujukan</span> WAJIB RS yang sama — ambil dari kandidat. <span class="ds-code">bpjs-code</span> bisa string "null" (non-BPJS) → tidak bisa jadi tujuan.</td></tr>
                                        <tr><td class="ds-td-strong">Tujuan dikunci kandidat</td><td class="ds-body-sm">Rujukan/Insert TIDAK memvalidasi tujuan ∈ kandidat — SIMRS yang mengunci (panel hanya mengizinkan pilih dari tabel kandidat).</td></tr>
                                        <tr><td class="ds-td-strong">Verifikasi sukses</td><td class="ds-body-sm">Cek <span class="ds-code">noRujukanSatuSehat</span> di response — kosong berarti GAGAL walau HTTP 200 (gangguan pusat kambuhan Jul–Agu 2026).</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted)">
                            Response tanpa kandidat sama sekali = <strong>memang tidak ada faskes yang cocok</strong>
                            (mis. diagnosa dinilai mampu ditangani sendiri) — itu jawaban sah, bukan error.
                        </p>
                    </section>

                    {{-- ====== ALUR FHIR ====== --}}
                    <section x-show="section === 'alur-fhir'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Alur</div>
                        <h1 class="ds-display-md mb-4">Jalur FHIR Langsung — IGD &amp; Ranap</h1>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-4" style="color:var(--muted)">Empat fase perujuk + satu langkah di faskes tujuan (Postman "30. Use Case - Rujukan Pasien V30062026")</div>
                            <div class="flex flex-wrap items-stretch gap-2">
                                @foreach ([
                                    ['1', 'Pra Permintaan', 'Task referral-pre-request'],
                                    ['2', 'Cari Kandidat', 'Task + Q100 kriteria + Q101 wilayah'],
                                    ['3', 'Tugas Rujukan', 'Bundle Task+CarePlan → approval'],
                                    ['3b', 'Jawaban Tujuan', 'RS tujuan PATCH accepted / rejected'],
                                    ['4', 'ServiceRequest', 'Nomor rujukan terbit'],
                                ] as [$no, $judul, $sub])
                                    <div class="flex-1 min-w-[150px] rounded-xl p-4" style="background:var(--surface-card)">
                                        <div class="ds-caption-up" style="color:var(--primary)">Fase {{ $no }}</div>
                                        <div class="ds-title-md" style="color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-body-sm" style="color:var(--muted)">{{ $sub }}</div>
                                    </div>
                                    @if (!$loop->last)
                                        <div class="self-center ds-title-md" style="color:var(--muted-soft)">→</div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Aturan krusial jalur FHIR</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Aturan</th><th>Detail</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Identifier unik SETIAP POST</td><td class="ds-body-sm"><span class="ds-code">Task.identifier.value</span> wajib UUID baru tiap kirim, TERMASUK RETRY. Reuse = response tanpa contained/output (menyesatkan) atau "Found duplicate: Task". Ini akar kasus paling sering di grup.</td></tr>
                                        <tr><td class="ds-td-strong">Kriteria per jalur</td><td class="ds-body-sm">IGD: 5 pertanyaan GAWAT DARURAT (linkId 000001–000005, minimal satu, TANPA validasi ICD). Ranap: Terapi/Tindakan ICD-9/Upaya Diagnosis (tepat satu).</td></tr>
                                        <tr><td class="ds-td-strong">Wilayah valueCoding</td><td class="ds-body-sm">Q101 wajib <span class="ds-code">valueCoding</span> administrative-area (valueString = 0 kandidat); kode tanpa titik (3504).</td></tr>
                                        <tr><td class="ds-td-strong">Task.owner = TUJUAN</td><td class="ds-body-sm">Pada bundle approval, owner = Organization faskes tujuan — tanpa ini RS tujuan tidak melihat rujukan masuk. CarePlan.author = Practitioner perujuk (mandatory).</td></tr>
                                        <tr><td class="ds-td-strong">CarePlan.category = jalur</td><td class="ds-body-sm">Ranap <span class="ds-code">736353004</span> Inpatient care plan (SNOMED) vs IGD <span class="ds-code">TK000068</span> Emergency care plan (kemkes) — SATU-SATUNYA beda bundle ranap dan gawat darurat. RS tujuan memakainya untuk memilah antrean, jadi salah kategori = permintaan mendarat di unit yang keliru.</td></tr>
                                        <tr><td class="ds-td-strong">Kotak masuk sisi tujuan</td><td class="ds-body-sm">Kalau KITA yang dirujuk: <span class="ds-code">GET Task?owner=&lt;org kita&gt;&amp;code=referral-approval-request&amp;_include=Task:based-on</span> lalu PATCH keputusan. Layarnya <strong>Rujukan → Persetujuan Rujukan Masuk</strong>, terpisah dari EMR.</td></tr>
                                        <tr><td class="ds-td-strong">Memantau jawaban tujuan</td><td class="ds-body-sm">Sisi perujuk membaca keputusan lewat <span class="ds-code">GET Task?code=referral-approval-request&amp;requester=&lt;org kita&gt;&amp;_include=Task:based-on</span> (parameter <span class="ds-code">&amp;encounter=</span> sah sebagai filter). Layarnya <strong>Rujukan → Pemantauan Rujukan Keluar</strong> — jawaban datang menyusul, bisa berjam-jam, jadi tidak dipantau dari EMR pasien satu per satu.</td></tr>
                                        <tr><td class="ds-td-strong">Jangan echo providerAtribute</td><td class="ds-body-sm">Extension kandidat (distance/strata/bpjs-code) adalah OUTPUT server — menyalinnya ke resource yang dikirim = ditolak validator.</td></tr>
                                        <tr><td class="ds-td-strong">1 CarePlan = 1 nomor</td><td class="ds-body-sm">Jangan tembak beberapa RS sekaligus; penerima punya ±15 menit sebelum perujuk disarankan pindah kandidat. Di staging, approval boleh dilewati (langsung ServiceRequest setelah bundle).</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    {{-- ====== LINTAS JALUR ====== --}}
                    <section x-show="section === 'alur-cross'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Alur</div>
                        <h1 class="ds-display-md mb-4">Lintas Jalur &amp; Internal RS</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Kasus lintas RS A → RS B</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Kasus</th><th>Jalur</th><th>Kriteria</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Rajal A → UGD B</td><td class="ds-body-sm">FHIR darurat (385868005)</td><td class="ds-body-sm">5 pertanyaan gawat darurat</td></tr>
                                        <tr><td class="ds-td-strong">UGD A → UGD B</td><td class="ds-body-sm">FHIR darurat (385868005)</td><td class="ds-body-sm">5 pertanyaan gawat darurat</td></tr>
                                        <tr><td class="ds-td-strong">UGD A → Ranap B</td><td class="ds-body-sm">FHIR ranap (737481003)</td><td class="ds-body-sm">Tepat satu (terapi/tindakan/upaya)</td></tr>
                                        <tr><td class="ds-td-strong">Rajal A → Ranap B</td><td class="ds-body-sm">FHIR ranap (737481003)</td><td class="ds-body-sm">Tepat satu</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="ds-body-sm" style="color:var(--muted); padding:12px 24px 16px">
                                Percobaan menembus tujuan IGD lewat jalur vclaim (<span class="ds-code">kodeSubSpesialis: "IGD"</span>)
                                DITOLAK "tidak valid" — endpoint vclaim memang khusus tujuan poli rawat jalan.
                            </p>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Perpindahan DI DALAM RS sendiri — BUKAN SRBK</div>
                            <ul class="ds-body-md space-y-1" style="max-width:66ch; list-style:disc; padding-left:20px">
                                <li><strong>RJ → UGD (RS sama)</strong>: episode baru UGD — pendaftaran UGD + SEP + Encounter baru.</li>
                                <li><strong>UGD → RI (RS sama)</strong>: modul Transfer ke RI (cara masuk tipe 7) + SEP ranap + Encounter ranap.</li>
                                <li>Tidak ada GetKriteria, tidak ada kandidat, tidak ada ServiceRequest rujukan.</li>
                            </ul>
                        </div>
                    </section>

                    {{-- ====== URL API & ENV ====== --}}
                    <section x-show="section === 'api-env'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Payload &amp; API</div>
                        <h1 class="ds-display-md mb-4">URL API &amp; Env</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--muted)">Ringkasan endpoint kedua jalur — detail request/response tiap endpoint ada di dua seksi katalog berikutnya.</p>

                        {{-- ── Jalur BPJS ── --}}
                        <div x-data="{ open: true }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Jalur BPJS — vclaim-sisrute-rest (dev/UAT)</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">HMAC VClaim</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL} = https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-sisrute-rest</div>
                                <p class="ds-body-sm mb-1">Auth : header signature HMAC pola VClaim — <span class="ds-code">x-cons-id</span>, <span class="ds-code">x-timestamp</span>, <span class="ds-code">x-signature</span>, <span class="ds-code">user_key</span></p>
                                <p class="ds-body-sm mb-3">Format : <strong>Json</strong> — response terbungkus <span class="ds-code">metaData</span> + <span class="ds-code">response</span> (dev plain; production terenkripsi)</p>
                                <div class="overflow-x-auto">
                                    <table class="ds-table">
                                        <thead><tr><th>Verb</th><th>Endpoint</th><th>Fungsi</th></tr></thead>
                                        <tbody>
                                            <tr><td class="ds-td-strong">POST</td><td class="ds-td-meta">{BASE URL}/Rujukan/GetKriteriaRujukan</td><td class="ds-body-sm">Kriteria + jejaring wilayah per ICD-10 (GET = 405!)</td></tr>
                                            <tr><td class="ds-td-strong">POST</td><td class="ds-td-meta">{BASE URL}/Rujukan/GetFaskesRujukan</td><td class="ds-body-sm">Daftar kandidat faskes</td></tr>
                                            <tr><td class="ds-td-strong">POST</td><td class="ds-td-meta">{BASE URL}/Rujukan/Insert</td><td class="ds-body-sm">Kirim rujukan (wrapper request.t_rujukan)</td></tr>
                                            <tr><td class="ds-td-strong">DELETE</td><td class="ds-td-meta">{BASE URL}/Rujukan/Delete</td><td class="ds-body-sm">Batalkan rujukan (verb DELETE, bukan POST)</td></tr>
                                            <tr><td class="ds-td-strong">GET</td><td class="ds-td-meta">{BASE URL}/Rujukan/GetSpesialistik</td><td class="ds-body-sm">Master kode spesialistik</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="ds-body-sm mt-3" style="color:var(--muted)">Production: base URL &amp; ketentuan header mengikuti katalog Trustmark BPJS. Jebakan: di server dev BPJS tertentu header <span class="ds-code">Content-Type</span> justru harus DILEPAS; production wajib pakai.</p>
                            </div>
                        </div>

                        {{-- ── Jalur FHIR ── --}}
                        <div x-data="{ open: true }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Jalur FHIR — SATUSEHAT Rujukan (staging)</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">Bearer OAuth2</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL} = https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/</div>
                                <p class="ds-body-sm mb-1">Auth : <span class="ds-code">Authorization: Bearer {token}</span> — token dari <span class="ds-code">{AUTH URL}/accesstoken?grant_type=client_credentials</span></p>
                                <p class="ds-body-sm mb-3">Format : <strong>Json (FHIR R4)</strong> — <span class="ds-code">identifier.value</span> WAJIB UUID baru tiap POST, termasuk retry</p>
                                <div class="overflow-x-auto">
                                    <table class="ds-table">
                                        <thead><tr><th>Verb</th><th>Endpoint</th><th>Fungsi</th></tr></thead>
                                        <tbody>
                                            <tr><td class="ds-td-strong">POST</td><td class="ds-td-meta">{AUTH URL}/accesstoken?grant_type=client_credentials</td><td class="ds-body-sm">Ambil token (form: client_id + client_secret)</td></tr>
                                            <tr><td class="ds-td-strong">POST</td><td class="ds-td-meta">{BASE URL}/Task</td><td class="ds-body-sm">Task pra-permintaan &amp; pencarian kandidat</td></tr>
                                            <tr><td class="ds-td-strong">GET</td><td class="ds-td-meta">{BASE URL}/Task?_id={taskId}</td><td class="ds-body-sm">Poll kandidat / status Task</td></tr>
                                            <tr><td class="ds-td-strong">POST</td><td class="ds-td-meta">{BASE URL}/ <span class="ds-body-sm">(root)</span></td><td class="ds-body-sm">Bundle transaction Task+CarePlan (referral-approval)</td></tr>
                                            <tr><td class="ds-td-strong">POST</td><td class="ds-td-meta">{BASE URL}/ServiceRequest</td><td class="ds-body-sm">Kirim rujukan → nomor terbit di identifier</td></tr>
                                            <tr><td class="ds-td-strong">PATCH</td><td class="ds-td-meta">{BASE URL}/Task/{taskId}</td><td class="ds-body-sm">Batal (JSON Patch, Content-Type application/json-patch+json)</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {{-- ── .env ── --}}
                        <div x-data="{ open: true }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">.env</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">secret = xxxx</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg" style="border:1px solid var(--hairline); border-top:0; overflow:hidden">
<pre class="ds-code" style="margin:0; padding:20px; background:var(--surface-dark); color:var(--on-dark-soft); overflow-x:auto"><span style="color:#8b948c"># ── Jalur BPJS (SISRUTE) — cons-id jenis SISRUTE, terdaftar terpisah dari vclaim biasa</span>
SISRUTE_URL="https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-sisrute-rest"
SISRUTE_CONS_ID="8334"
SISRUTE_SECRET_KEY="xxxx"
SISRUTE_USER_KEY="xxxx"
SISRUTE_KDPPK="0184R006"   <span style="color:#8b948c"># kode faskes env dev (MADINAH JST)</span>

<span style="color:#8b948c"># ── Jalur FHIR (SATUSEHAT Rujukan) — credential KHUSUS staging dari tim SATUSEHAT (japri)</span>
SATUSEHAT_AUTH_URL="https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/"
SATUSEHAT_BASE_URL="https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/"
SATUSEHAT_CLIENT_ID="xxxx"
SATUSEHAT_SECRET_ID="xxxx"

<span style="color:#8b948c"># ── Dipakai bersama (sudah ada) — org id SATUSEHAT production RS</span>
SATUSEHAT_ORGANIZATION_ID="100027469"</pre>
                            </div>
                        </div>
                    </section>

                    {{-- ====== CONTOH JSON RJ (katalog gaya Trust Mark) ====== --}}
                    <section x-show="section === 'json-rj'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Payload &amp; API</div>
                        <h1 class="ds-display-md mb-4">Katalog Endpoint — Jalur RJ (vclaim-sisrute)</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--muted)">Klik tiap endpoint untuk membuka detail. Response BPJS terbungkus <span class="ds-code">metaData</span> + <span class="ds-code">response</span> (dev plain JSON; production terenkripsi pola vclaim).</p>

                        {{-- ── Get Kriteria Rujukan ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Get Kriteria Rujukan</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">POST</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/{Service Name}/Rujukan/GetKriteriaRujukan</div>
                                <p class="ds-body-sm mb-1">Fungsi : Kriteria rujukan + jejaring wilayah per diagnosa</p>
                                <p class="ds-body-sm mb-1">Method : <strong>POST</strong> <span style="color:var(--muted)">(GET dibalas 405 Method Not Allowed — temuan live 11/08/26)</span></p>
                                <p class="ds-body-sm mb-1">Format : <strong>Json</strong></p>
                                <p class="ds-body-sm mb-1">Parameter : <strong>kodeDiagnosa</strong> (ICD-10 rinci ber-titik), <strong>kodeFaskesSatuSehat</strong>, encounter (opsional)</p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
    "kodeDiagnosa": "I10.1",
    "kodeFaskesSatuSehat": "100027469",
    "encounter": { "reference": "Encounter/18fa6f17-b87f-4d45-ba3f-4a52a3d44746" }
}</pre>@endverbatim
                                </div>
                                <div class="ds-card-dark" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Response — linkId DINAMIS per ICD-10, jangan di-hardcode</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
    "metaData": { "code": 200, "message": "Sukses" },
    "response": {
        "kriteriaRujukan": [
            { "linkId": "51947,69587", "text": "Terapi",          "type": "boolean" },
            { "linkId": "27038,44678", "text": "Tindakan Medis",  "type": "text"    },
            { "linkId": "2129,19769",  "text": "Upaya Diagnosis", "type": "boolean" }
        ],
        "JejaringWilayah": [{
            "linkId": "1", "text": "Jejaring wilayah rujukan", "type": "group",
            "item": [
                { "linkId": "1.1", "text": "Provinsi", "type": "choice",
                  "answerOption": [ { "valueCoding": { "code": "35", "display": "JAWA TIMUR" } } ] },
                { "linkId": "1.2", "text": "Kabupaten/Kota", "type": "choice",
                  "answerOption": [ { "valueCoding": { "code": "3504", "display": "KAB. TULUNGAGUNG" } } ] }
            ]
        }]
    }
}</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── Get Faskes Rujukan ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Get Faskes Rujukan (Kandidat)</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">POST</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/{Service Name}/Rujukan/GetFaskesRujukan</div>
                                <p class="ds-body-sm mb-1">Fungsi : Daftar kandidat faskes tujuan (match kompetensi)</p>
                                <p class="ds-body-sm mb-1">Method : <strong>POST</strong></p>
                                <p class="ds-body-sm mb-1">Format : <strong>Json</strong></p>
                                <p class="ds-body-sm mb-1">Catatan : kriteria TEPAT SATU terisi; <span class="ds-code">estimasiRujuk</span> dd-mm-yyyy vs <span class="ds-code">tglRencanaKunjungan</span> yyyy-mm-dd</p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
    "kodeFaskesSatuSehat": "100027469",
    "kodeSpesialis": "JAN",
    "kodeSarana": "",
    "kodeDiagnosa": "I10.1",
    "tglRencanaKunjungan": "2026-08-12",
    "estimasiRujuk": "12-08-2026",
    "kriteriaRujukan": { "item": [
        { "linkId": "27038,44678", "text": "Tindakan Medis", "answer": [ { "valueString": "01.24" } ] }
    ]},
    "codeJejaringWilayah": {
        "kodePropinsi": "35", "namaPropinsi": "JAWA TIMUR",
        "kodeKabupaten": "3504", "namaKabupaten": "KAB. TULUNGAGUNG"
    },
    "encounter": { "reference": "Encounter/18fa6f17-b87f-4d45-ba3f-4a52a3d44746" }
}</pre>@endverbatim
                                </div>
                                <div class="ds-card-dark" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Response asli (log 11/08/26) — nmppk = NAMA, nmkc = KOTA</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
    "metaData": { "code": "200", "message": "Sukses" },
    "response": { "count": 6, "list": [
        { "kodeFaskesSatuSehat": "Organization/100027694",
          "kdppk": "0184R003", "nmppk": "ERA MEDIKA", "nmkc": "TULUNGAGUNG",
          "strataSatuSehat": "Dasar", "alamatPpk": "JL. RAYA PULOSARI NGUNUT",
          "telpPpk": "0355)-398706", "kelas": "C",
          "kapasitas": 15, "jmlRujuk": 0, "persentase": 0, "distance": 2.67 }
    ]}
}</pre>@endverbatim
                                    <div class="px-4 py-3 text-xs" style="color:var(--on-dark-soft)">
                                        <strong>Tiga jebakan pemetaan</strong> (ketiganya pernah terjadi):
                                        <span class="block mt-1">1. Nama faskes ada di <code>nmppk</code>. <code>nmkc</code> itu nama kota — dipakai sebagai nama membuat semua baris tampil sama ("TULUNGAGUNG").</span>
                                        <span class="block">2. <code>kodeFaskesSatuSehat</code> datang berawalan <code>Organization/</code>, sedangkan kode milik kita dikirim polos. Buang awalannya sebelum dipakai sebagai <code>kdppkSatuSehatTujuanRujukan</code>, kalau tidak BPJS menolak "PPK tidak ditemukan di pemetaan".</span>
                                        <span class="block">3. Tidak ada field <code>jadwal</code> di response ini. Yang tersedia untuk membantu pemilihan: <code>kelas</code>, <code>distance</code> (km), dan beban <code>jmlRujuk</code>/<code>kapasitas</code>.</span>
                                        <span class="block mt-1"><code>kdppk</code> bisa berisi string <code>"null"</code> = RS non-BPJS, tak boleh jadi tujuan rujukan BPJS.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ── Insert Rujukan ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Insert Rujukan</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">POST</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/{Service Name}/Rujukan/Insert</div>
                                <p class="ds-body-sm mb-1">Fungsi : Kirim rujukan RJ → poli RS lain (BPJS meneruskan ke SATUSEHAT)</p>
                                <p class="ds-body-sm mb-1">Method : <strong>POST</strong></p>
                                <p class="ds-body-sm mb-1">Format : <strong>Json</strong> — wrapper <span class="ds-code">request.t_rujukan</span></p>
                                <p class="ds-body-sm mb-1">Catatan : <span class="ds-code">ppkDirujuk</span> ↔ <span class="ds-code">kdppkSatuSehatTujuanRujukan</span> wajib RS yang sama (dari kandidat); server TIDAK memvalidasi tujuan ∈ kandidat</p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
  "request": { "t_rujukan": {
      "noSep": "0184R0061126V000001",
      "tglRujukan": "2026-08-11",
      "tglRencanaKunjungan": "2026-08-12",
      "ppkDirujuk": "0342R074",
      "jnsPelayanan": "2",
      "catatan": "Kontrol hipertensi lanjutan",
      "diagRujukan": "I10.1",
      "tipeRujukan": "0",
      "poliRujukan": "JAN",
      "user": "namauser",
      "satuSehatRujukan": {
          "kodeFaskesSatuSehat": "100027469",
          "idPasienSatuSehat": "P20395452616",
          "kdppkSatuSehatTujuanRujukan": "100027550",
          "kdDokterSatuSehat": "10009880728",
          "encounter": { "reference": "18fa6f17-b87f-4d45-ba3f-4a52a3d44746" },
          "patientInstruction": "Rujukan ke RS CONTOH A",
          "kriteriaRujukan": { "item": [
              { "linkId": "27038,44678", "text": "Tindakan Medis", "answer": [ { "valueString": "01.24" } ] }
          ]},
          "keteranganRujukan": "Kontrol hipertensi lanjutan",
          "codeJejaringWilayah": {
              "kodePropinsi": "35", "namaPropinsi": "JAWA TIMUR",
              "kodeKabupaten": "3504", "namaKabupaten": "KAB. TULUNGAGUNG"
          }
      }
  }}
}</pre>@endverbatim
                                </div>
                                <div class="ds-card-dark" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Response — SUKSES = noRujukanSatuSehat TERBIT</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">// SUKSES
{
    "metaData": { "code": 200, "message": "Sukses" },
    "response": { "rujukan": {
        "noRujukan": "0184R0060826B000001",
        "noRujukanSatuSehat": "7371373260523101",
        "serviceRequestId": "c4fcc228-ef3b-42b4-af36-fe341d37b1ca"
    }}
}

// GAGAL — contoh nyata di lapangan
{ "metaData": { "code": 400, "message": "Satu Sehat Tujuan Rujukan tidak sesuai dengan PPK Dirujuk" }, "response": null }
{ "metaData": { "code": 500, "message": "Value was either too large or too small for a Decimal." },   "response": null }</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── Delete Rujukan ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Delete Rujukan</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">DELETE</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/{Service Name}/Rujukan/Delete</div>
                                <p class="ds-body-sm mb-1">Fungsi : Batalkan/hapus rujukan</p>
                                <p class="ds-body-sm mb-1">Method : <strong>DELETE</strong> <span style="color:var(--muted)">(POST dibalas "No Mapping Rule matched")</span></p>
                                <p class="ds-body-sm mb-1">Format : <strong>Json</strong></p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
    "request": { "t_rujukan": { "noRujukan": "0184R0060826B000001", "user": "namauser" } }
}</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── List Spesialistik ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Get Spesialistik</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">GET</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/{Service Name}/Rujukan/GetSpesialistik</div>
                                <p class="ds-body-sm mb-1">Fungsi : Master kode spesialis/subspesialis FKRTL</p>
                                <p class="ds-body-sm mb-1">Method : <strong>GET</strong> — tanpa body</p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Response (ringkas)</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
    "metaData": { "code": 200, "message": "Sukses" },
    "response": { "list": [
        { "kode": "JAN", "nama": "JANTUNG" },
        { "kode": "INT", "nama": "PENYAKIT DALAM" }
    ]}
}</pre>@endverbatim
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- ====== CONTOH JSON FHIR (katalog gaya Trust Mark) ====== --}}
                    <section x-show="section === 'json-fhir'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Payload &amp; API</div>
                        <h1 class="ds-display-md mb-4">Katalog Endpoint — Jalur FHIR (IGD &amp; Ranap)</h1>
                        <p class="ds-body-sm mb-4" style="color:var(--muted)">Auth Bearer token OAuth2 (credential khusus staging). Semua <span class="ds-code">identifier.value</span> wajib UUID BARU setiap POST, termasuk retry.</p>

                        {{-- ── Generate Token ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Generate Token</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">POST</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{AUTH URL}/accesstoken?grant_type=client_credentials</div>
                                <p class="ds-body-sm mb-1">Fungsi : Ambil access token (cache ±58 menit)</p>
                                <p class="ds-body-sm mb-1">Method : <strong>POST</strong></p>
                                <p class="ds-body-sm mb-1">Content-Type : <strong>application/x-www-form-urlencoded</strong></p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request (form) — secret ditulis xxxx</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">client_id=xxxx&amp;client_secret=xxxx</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── Task Pra Permintaan ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Task — Pra Permintaan Rujukan</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">POST</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/Task</div>
                                <p class="ds-body-sm mb-1">Fungsi : Fase 1 — pra permintaan kandidat (code <span class="ds-code">referral-pre-request</span>)</p>
                                <p class="ds-body-sm mb-1">Method : <strong>POST</strong> · Format : <strong>Json (FHIR R4)</strong></p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request (dipadatkan)</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
  "resourceType": "Task",
  "identifier": [ { "system": "http://sys-ids.kemkes.go.id/task/100027469", "value": "{uuid-BARU}" } ],
  "status": "requested", "intent": "instance-order", "priority": "routine",
  "code": { "coding": [ { "system": "http://terminology.kemkes.go.id", "code": "referral-pre-request" } ] },
  "requester": { "reference": "Organization/100027469" },
  "owner":     { "reference": "Organization/100027469" },
  "encounter": { "reference": "Encounter/{uuid-encounter}" },
  "input": [ { "type": { "coding": [ { "code": "primary-diagnosis" } ] },
               "valueCoding": { "system": "http://hl7.org/fhir/sid/icd-10", "code": "I61.9" } } ]
}</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── Task Pencarian Kandidat ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Task — Pencarian Kandidat Faskes</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">POST</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/Task</div>
                                <p class="ds-body-sm mb-1">Fungsi : Fase 2 — cari kandidat (code <span class="ds-code">request-referral-candidate</span>; contained Q100 kriteria + Q101 wilayah)</p>
                                <p class="ds-body-sm mb-1">Method : <strong>POST</strong> · Format : <strong>Json (FHIR R4)</strong></p>
                                <p class="ds-body-sm mb-1">Catatan : wilayah WAJIB <span class="ds-code">valueCoding</span> (valueString = 0 kandidat); kode wilayah tanpa titik</p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request (dipadatkan)</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
  "resourceType": "Task",
  "contained": [
    { "resourceType": "QuestionnaireResponse", "id": "qr-kriteria-{uuid}",
      "questionnaire": "https://fhir.kemkes.go.id/Questionnaire/Q100", "status": "completed",
      "subject": { "reference": "Patient/P20395452616" },
      "encounter": { "reference": "Encounter/{uuid-encounter}" },
      "item": [
          // IGD → grup "GAWAT DARURAT" linkId 000001-000005 (valueBoolean)
          // Ranap → 3 item: Terapi/Pengobatan, Tindakan Medis (valueString ICD-9), Upaya Diagnosis
      ] },
    { "resourceType": "QuestionnaireResponse", "id": "qr-area-{uuid}",
      "questionnaire": "https://fhir.kemkes.go.id/Questionnaire/Q101", "status": "completed",
      "item": [ { "linkId": "1", "text": "Jejaring wilayah rujukan", "item": [
          { "linkId": "1.1", "text": "Provinsi",
            "answer": [ { "valueCoding": { "system": "http://sys-ids.kemkes.go.id/administrative-area", "code": "35", "display": "JAWA TIMUR" } } ] },
          { "linkId": "1.2", "text": "Kabupaten/Kota",
            "answer": [ { "valueCoding": { "system": "http://sys-ids.kemkes.go.id/administrative-area", "code": "3504", "display": "KABUPATEN TULUNGAGUNG" } } ] }
      ] } ] }
  ],
  "identifier": [ { "system": "http://sys-ids.kemkes.go.id/task/100027469", "value": "{uuid-BARU-tiap-post}" } ],
  "status": "requested", "intent": "instance-order", "priority": "routine",
  "code": { "coding": [ { "system": "http://terminology.kemkes.go.id", "code": "request-referral-candidate" } ] },
  "for": { "reference": "Patient/P20395452616" },
  "requester": { "reference": "Organization/100027469" },
  "owner":     { "reference": "Organization/100027469" },
  "encounter": { "reference": "Encounter/{uuid-encounter}" },
  "input": [
    { "type": { "coding": [ { "code": "referral-criteria" } ] }, "valueReference": { "reference": "#qr-kriteria-{uuid}" } },
    { "type": { "coding": [ { "code": "area" } ] },              "valueReference": { "reference": "#qr-area-{uuid}" } },
    { "type": { "coding": [ { "system": "http://snomed.info/sct", "code": "119270007" } ] },
      "valueCoding": { "system": "http://snomed.info/sct",
          "code": "385868005", "display": "Emergency treatment management" } },
          // ranap: "code": "737481003", "display": "Inpatient care management"
    { "type": { "coding": [ { "code": "primary-diagnosis" } ] },
      "valueCoding": { "system": "http://hl7.org/fhir/sid/icd-10", "code": "I61.9" } }
  ]
}</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── Get Task (poll kandidat) ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Get Task — Hasil Kandidat</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">GET</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/Task?_id={Parameter 1}</div>
                                <p class="ds-body-sm mb-1">Fungsi : Poll status Task + kandidat di <span class="ds-code">output[]</span></p>
                                <p class="ds-body-sm mb-1">Method : <strong>GET</strong></p>
                                <p class="ds-body-sm mb-1">Parameter 1 : <strong>id Task pencarian kandidat</strong></p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Response — extension providerAtribute = OUTPUT server, jangan di-echo balik</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
  "resourceType": "Task", "id": "8db92d1d-...", "status": "completed",
  "output": [
    { "type": { "coding": [ { "code": "candidate" } ] },
      "valueReference": { "reference": "Organization/100027550", "display": "RS CONTOH A" },
      "extension": [ { "url": ".../StructureDefinition/providerAtribute", "extension": [
          { "url": "distance",       "valueQuantity": { "value": 4.2, "code": "km" } },
          { "url": "estimated-time", "valueQuantity": { "value": 12,  "code": "minute" } },
          { "url": "strata",         "valueString": "madya" },
          { "url": "bpjs-code",      "valueString": "0342R074" },
          { "url": "kemkes-code",    "valueString": "100027550" }
      ] } ] }
  ]
}</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── Bundle Approval ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Bundle — Tugas Rujukan (Approval)</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">POST</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/ <span class="ds-body-sm">(root — Bundle transaction)</span></div>
                                <p class="ds-body-sm mb-1">Fungsi : Fase 3 — kirim tugas rujukan ke faskes tujuan (meta.tag <span class="ds-code">referral-approval</span>)</p>
                                <p class="ds-body-sm mb-1">Method : <strong>POST</strong> · Format : <strong>Json (FHIR R4 Bundle)</strong></p>
                                <p class="ds-body-sm mb-1">Catatan : <span class="ds-code">Task.owner</span> = Organization TUJUAN; <span class="ds-code">CarePlan.author</span> = Practitioner perujuk (mandatory)</p>
                                <p class="ds-body-sm mb-1">Kategori CarePlan menentukan LAYANAN yang diminta — <strong>IGD</strong> <span class="ds-code">TK000068</span> Emergency care plan; <strong>Ranap</strong> <span class="ds-code">736353004</span> Inpatient care plan (SNOMED). Salah kategori = permintaan masuk ke antrean yang keliru di RS tujuan.</p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request (dipadatkan)</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
  "resourceType": "Bundle", "type": "transaction",
  "meta": { "tag": [ { "system": "http://terminology.kemkes.go.id", "code": "referral-approval" } ] },
  "entry": [
    { "fullUrl": "urn:uuid:{uuid-task}",
      "resource": { "resourceType": "Task",
          "identifier": [ { "system": "http://sys-ids.kemkes.go.id/task/100027469", "value": "{uuid-BARU}" } ],
          "basedOn": [ { "reference": "urn:uuid:{uuid-careplan}" } ],
          "status": "requested", "intent": "instance-order",
          "code": { "coding": [ { "code": "referral-approval-request" } ] },
          "requester": { "reference": "Organization/100027469" },
          "owner":     { "reference": "Organization/100027550" },
          "input": [ { "type": { "coding": [ { "code": "referral-task" } ] },
                       "valueReference": { "reference": "Organization/100027550", "display": "RS CONTOH A" } } ] },
      "request": { "method": "POST", "url": "Task" } },
    { "fullUrl": "urn:uuid:{uuid-careplan}",
      "resource": { "resourceType": "CarePlan",
          "identifier": [ { "system": "http://sys-ids.kemkes.go.id/careplan/100027469", "value": "{uuid-BARU}" } ],
          "status": "active", "intent": "plan",
          "category": [ { "coding": [ { "code": "TK000068" } ] },
                        { "coding": [ { "system": "http://snomed.info/sct", "code": "3457005" } ] } ],
          "title": "Rencana Rujukan Pasien", "description": "...",
          "author": { "reference": "Practitioner/10009880728", "display": "dr. Contoh" },
          "activity": [ { "detail": { "kind": "ServiceRequest", "status": "not-started",
              "code": { "coding": [ { "system": ".../CodeSystem/clinical-speciality",
                                      "code": "L03", "display": "Pelayanan Gawat Darurat" } ] } } } ] },
      "request": { "method": "POST", "url": "CarePlan" } }
  ]
}</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── ServiceRequest ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">ServiceRequest — Kirim Rujukan</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">POST</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/ServiceRequest</div>
                                <p class="ds-body-sm mb-1">Fungsi : Fase 4 — kirim rujukan; SUKSES = identifier <span class="ds-code">referral-number-satusehat</span> terbit</p>
                                <p class="ds-body-sm mb-1">Method : <strong>POST</strong> · Format : <strong>Json (FHIR R4)</strong></p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request (dipadatkan)</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
  "resourceType": "ServiceRequest",
  "identifier": [ { "system": "http://sys-ids.kemkes.go.id/servicerequest/100027469", "value": "{uuid-BARU}" } ],
  "basedOn": [ { "reference": "CarePlan/{id-dari-bundle}" } ],
  "status": "active", "intent": "original-order", "priority": "stat",
  "category": [ { "coding": [ { "system": "http://snomed.info/sct", "code": "3457005" } ] } ],
  "code": { "coding": [ { "system": "http://snomed.info/sct", "code": "385868005" } ], "text": "..." },
  "subject":   { "reference": "Patient/P20395452616" },
  "encounter": { "reference": "Encounter/{uuid-encounter}" },
  "requester": { "reference": "Organization/100027469" },
  "performer": [ { "reference": "Organization/100027550", "display": "RS CONTOH A" } ],
  "supportingInfo": [ { "reference": "Task/{id-task-approval}" } ]
}</pre>@endverbatim
                                </div>
                                <div class="ds-card-dark" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Response — perhatikan identifier kedua</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">{
  "resourceType": "ServiceRequest", "id": "0ee428eb-...",
  "identifier": [
    { "system": "http://sys-ids.kemkes.go.id/servicerequest/100027469", "value": "..." },
    { "system": "http://sys-ids.kemkes.go.id/referral-number-satusehat", "value": "32735172607102001" }
  ],
  "status": "active"
}</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── Patch Cancel ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Task — Batalkan (Cancel)</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">PATCH</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/Task/{Parameter 1}</div>
                                <p class="ds-body-sm mb-1">Fungsi : Batalkan tugas rujukan</p>
                                <p class="ds-body-sm mb-1">Method : <strong>PATCH</strong></p>
                                <p class="ds-body-sm mb-1">Content-Type : <strong>application/json-patch+json</strong></p>
                                <p class="ds-body-sm mb-1">Parameter 1 : <strong>id Task approval</strong></p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request (JSON Patch)</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">[
    { "op": "replace", "path": "/status", "value": "cancelled" }
]</pre>@endverbatim
                                </div>
                            </div>
                        </div>

                        {{-- ── Get Task by Owner (kotak masuk faskes tujuan) ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Get Task by Owner — Kotak Masuk Rujukan</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">GET</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/Task?owner={Org kita}&amp;code=referral-approval-request&amp;_include=Task:based-on</div>
                                <p class="ds-body-sm mb-1">Fungsi : Kita jadi <strong>FASKES TUJUAN</strong> — daftar permintaan rujukan yang dikirim RS lain</p>
                                <p class="ds-body-sm mb-1">Method : <strong>GET</strong></p>
                                <p class="ds-body-sm mb-1">Catatan : <span class="ds-code">_include=Task:based-on</span> praktis WAJIB — nama pasien, keterangan klinis, dan layanan yang diminta ada di <strong>CarePlan</strong>, bukan di Task. Tanpa include, kotak masuk cuma berisi UUID.</p>
                                <div class="ds-card-outline my-4" style="padding:0; overflow:hidden">
                                    <div class="overflow-x-auto">
                                        <table class="ds-table">
                                            <thead><tr><th>Yang dibaca</th><th>Dari mana</th></tr></thead>
                                            <tbody>
                                                <tr><td class="ds-td-strong">Jalur diminta (Ranap / IGD)</td><td class="ds-body-sm"><span class="ds-code">CarePlan.category</span> — ranap <span class="ds-code">736353004</span> Inpatient care plan (SNOMED); IGD <span class="ds-code">TK000068</span> Emergency care plan (kemkes). <strong>Satu-satunya beda bundle ranap vs gawat darurat.</strong></td></tr>
                                                <tr><td class="ds-td-strong">Nama pasien</td><td class="ds-body-sm"><span class="ds-code">CarePlan.subject.display</span> (Task hanya menyimpan reference)</td></tr>
                                                <tr><td class="ds-td-strong">Keterangan klinis</td><td class="ds-body-sm"><span class="ds-code">CarePlan.description</span> · layanan di <span class="ds-code">activity[0].detail.code</span></td></tr>
                                                <tr><td class="ds-td-strong">Nama RS perujuk</td><td class="ds-body-sm">TIDAK ikut di <span class="ds-code">Task.requester</span> → <span class="ds-code">GET Organization/{id}</span> lalu cache (jangan cache kegagalan)</td></tr>
                                                <tr><td class="ds-td-strong">Sudah dijawab?</td><td class="ds-body-sm"><span class="ds-code">Task.output[].valueCoding.code</span> = accepted/rejected; <span class="ds-code">status:"cancelled"</span> = dibatalkan perujuk, jangan tawarkan tombol jawab</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <p class="ds-body-sm mb-1">Sisi perujuk memantau keputusan lewat <span class="ds-code">GET {BASE URL}/Task?code=referral-approval-request&amp;requester={Org perujuk}</span> — parameter <span class="ds-code">&amp;encounter=</span> sah sebagai filter (konfirmasi tim SATUSEHAT 14/08/26), jadi tak perlu menyapu seluruh Task RS.</p>
                            </div>
                        </div>

                        {{-- ── Patch Respon Approval (accept/reject) ── --}}
                        <div x-data="{ open: false }" class="mb-2">
                            <button type="button" x-on:click="open = !open"
                                class="w-full flex items-center gap-2 px-4 py-3 text-left rounded-lg transition"
                                style="background:var(--surface-card); color:var(--muted)">
                                <span class="text-xs font-semibold tracking-wide uppercase">Task — Setujui / Tolak Rujukan Masuk</span>
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full" style="background:var(--success-tint); color:var(--success-deep)">PATCH</span>
                                <span class="ml-auto" x-text="open ? '−' : '+'"></span>
                            </button>
                            <div x-show="open" x-cloak class="rounded-b-lg px-5 py-4" style="border:1px solid var(--hairline); border-top:0; background:var(--canvas)">
                                <div class="ds-title-lg mb-3" style="font-style:italic; color:var(--ink)">{BASE URL}/Task/{Parameter 1}</div>
                                <p class="ds-body-sm mb-1">Fungsi : Jawaban faskes tujuan atas tugas rujukan — status jadi <span class="ds-code">completed</span> + keputusan di <span class="ds-code">output</span></p>
                                <p class="ds-body-sm mb-1">Method : <strong>PATCH</strong></p>
                                <p class="ds-body-sm mb-1">Content-Type : <strong>application/json-patch+json</strong></p>
                                <p class="ds-body-sm mb-1">Parameter 1 : <strong>id Task dari kotak masuk</strong></p>
                                <div class="ds-card-dark my-4" style="padding:0; overflow:hidden">
                                    <div class="px-4 py-2" style="background:var(--surface-dark-soft)"><span class="ds-caption-up" style="color:var(--on-dark-soft)">Request (JSON Patch) — ganti code jadi "rejected" untuk penolakan</span></div>
@verbatim<pre class="ds-code" style="margin:0; padding:20px; color:var(--on-dark-soft); overflow-x:auto">[
    { "op": "replace", "path": "/status", "value": "completed" },
    { "op": "add", "path": "/output", "value": [
        { "type": { "coding": [ { "system": "http://terminology.kemkes.go.id",
                                  "code": "response-referral-task",
                                  "display": "Response referral task" } ],
                    "text": "Respon atas Task Rujukan" },
          "valueCoding": { "system": "http://hl7.org/fhir/task-status",
                           "code": "accepted", "display": "Accepted" } }
    ] }
]</pre>@endverbatim
                                </div>
                                <p class="ds-body-sm mb-1">Postman juga menyediakan varian <strong>PUT Task utuh</strong>; kita pakai PATCH — lebih pendek dan tidak berisiko menimpa field yang tidak kita kirim.</p>
                                <p class="ds-body-sm">Sesudah <em>accepted</em>, perujuk melanjutkan ke ServiceRequest. <strong>Pendaftaran kunjungan pasien rujukan di sisi RS tujuan</strong> (Postman "Faskes Rujukan - Pendaftaran Kunjungan Rujukan") adalah langkah terpisah yang belum dibangun.</p>
                            </div>
                        </div>
                    </section>

                    {{-- ====== LOKASI PANEL ====== --}}
                    {{-- ====== ALUR PROGRAM & DIAGRAM ====== --}}
                    <section x-show="section === 'simrs-alur'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Di SIMRS</div>
                        <h1 class="ds-display-md mb-4">Alur Program &amp; Diagram</h1>
                        <p class="ds-body-md mb-6" style="max-width:66ch">
                            Satu rujukan hidup di <strong>dua rumah sakit dan tiga layar</strong>. Diagram di bawah
                            memperlihatkan siapa memanggil apa, kapan, dan layar mana yang membacanya — supaya jelas
                            kenapa rujukan masuk dan rujukan keluar dibuat sebagai layar terpisah dari EMR.
                        </p>

                        {{-- ── DIAGRAM 1: siklus jalur FHIR ── --}}
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">
                                Diagram 1 — Siklus rujukan jalur FHIR (IGD &amp; Ranap)
                            </div>
                            <div class="overflow-x-auto">
                                <svg viewBox="0 0 900 430" style="min-width:820px; width:100%; font-family:inherit">
                                    <defs>
                                        <marker id="panah-alur" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7"
                                            markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--muted)" />
                                        </marker>
                                    </defs>

                                    {{-- kepala lajur --}}
                                    <rect x="20" y="16" width="200" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="120" y="40" text-anchor="middle" font-size="13" font-weight="600" fill="var(--ink)">RS PERUJUK</text>
                                    <text x="120" y="58" text-anchor="middle" font-size="11" fill="var(--muted)">EMR RJ / UGD / RI</text>

                                    <rect x="350" y="16" width="200" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="450" y="40" text-anchor="middle" font-size="13" font-weight="600" fill="var(--ink)">SATUSEHAT</text>
                                    <text x="450" y="58" text-anchor="middle" font-size="11" fill="var(--muted)">Rujukan Nasional (FHIR)</text>

                                    <rect x="680" y="16" width="200" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="780" y="40" text-anchor="middle" font-size="13" font-weight="600" fill="var(--ink)">RS TUJUAN</text>
                                    <text x="780" y="58" text-anchor="middle" font-size="11" fill="var(--muted)">admisi ranap / IGD</text>

                                    {{-- garis hidup --}}
                                    <line x1="120" y1="76" x2="120" y2="410" stroke="var(--muted-soft)" stroke-width="1" stroke-dasharray="4 5" />
                                    <line x1="450" y1="76" x2="450" y2="410" stroke="var(--muted-soft)" stroke-width="1" stroke-dasharray="4 5" />
                                    <line x1="780" y1="76" x2="780" y2="410" stroke="var(--muted-soft)" stroke-width="1" stroke-dasharray="4 5" />

                                    {{-- 1 kirim tugas --}}
                                    <text x="285" y="102" text-anchor="middle" font-size="11" font-weight="600" fill="var(--ink)">1 · POST Bundle Task + CarePlan</text>
                                    <line x1="120" y1="112" x2="444" y2="112" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-alur)" />
                                    <text x="285" y="128" text-anchor="middle" font-size="10" fill="var(--muted)">Task.owner = RS tujuan · CarePlan.category = jalur</text>

                                    {{-- 2 kotak masuk --}}
                                    <text x="615" y="172" text-anchor="middle" font-size="11" font-weight="600" fill="var(--ink)">2 · GET Task?owner= …&amp;_include=Task:based-on</text>
                                    <line x1="450" y1="182" x2="774" y2="182" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-alur)" />
                                    <text x="615" y="198" text-anchor="middle" font-size="10" fill="var(--primary)">layar /rujukan/masuk</text>

                                    {{-- 3 jawaban --}}
                                    <text x="615" y="242" text-anchor="middle" font-size="11" font-weight="600" fill="var(--ink)">3 · PATCH Task → completed</text>
                                    <line x1="780" y1="252" x2="456" y2="252" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-alur)" />
                                    <text x="615" y="268" text-anchor="middle" font-size="10" fill="var(--muted)">output accepted / rejected</text>

                                    {{-- 4 perujuk memantau --}}
                                    <text x="285" y="312" text-anchor="middle" font-size="11" font-weight="600" fill="var(--ink)">4 · GET Task?requester= …&amp;_include</text>
                                    <line x1="450" y1="322" x2="126" y2="322" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-alur)" />
                                    <text x="285" y="338" text-anchor="middle" font-size="10" fill="var(--primary)">layar /rujukan/keluar — tab Ranap &amp; Gawat Darurat</text>

                                    {{-- 5 servicerequest --}}
                                    <text x="285" y="382" text-anchor="middle" font-size="11" font-weight="600" fill="var(--ink)">5 · POST ServiceRequest</text>
                                    <line x1="120" y1="392" x2="444" y2="392" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-alur)" />
                                    <text x="285" y="408" text-anchor="middle" font-size="10" fill="var(--muted)">hanya bila accepted · nomor rujukan terbit di sini</text>
                                </svg>
                            </div>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Langkah 1–5 dipetakan ke kode</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Langkah</th><th>Dikerjakan di</th><th>Method / layar</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">1 · Kirim tugas rujukan</td><td class="ds-body-sm">Panel rujukan di EMR RJ / UGD / RI</td><td class="ds-td-meta">rujukanBundleApproval()</td></tr>
                                        <tr><td class="ds-td-strong">2 · Baca kotak masuk</td><td class="ds-body-sm">Menu Rujukan → Persetujuan Rujukan Masuk</td><td class="ds-td-meta">rujukanTaskMasuk() → /rujukan/masuk</td></tr>
                                        <tr><td class="ds-td-strong">3 · Setujui / tolak</td><td class="ds-body-sm">Modal di layar yang sama</td><td class="ds-td-meta">rujukanTaskRespon()</td></tr>
                                        <tr><td class="ds-td-strong">4 · Pantau jawaban</td><td class="ds-body-sm">Menu Rujukan → Pemantauan Rujukan Keluar</td><td class="ds-td-meta">rujukanTaskByRequester() → /rujukan/keluar</td></tr>
                                        <tr><td class="ds-td-strong">5 · Terbitkan rujukan</td><td class="ds-body-sm">Kembali ke panel EMR pasien</td><td class="ds-td-meta">ServiceRequest → identifier referral-number-satusehat</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <ul class="ds-body-md space-y-1 mt-4" style="max-width:66ch; list-style:disc; padding-left:20px">
                                <li><strong>Langkah 2 dan 4 tidak terjadi seketika.</strong> Jawaban RS tujuan bisa datang
                                    berjam-jam kemudian — itulah alasan keduanya jadi layar daftar tersendiri, bukan panel di
                                    EMR satu pasien. Petugas tak perlu ingat siapa saja yang dirujuk hari itu.</li>
                                <li><strong>Langkah 5 belum otomatis.</strong> Disetujui ≠ rujukan sah; nomor baru terbit
                                    setelah ServiceRequest dikirim dari panel EMR pasien. Layar pemantauan mengingatkan ini
                                    di modal rinciannya.</li>
                                <li>Perujuk punya <strong>±15 menit</strong> sebelum disarankan pindah kandidat bila belum
                                    dijawab. Satu CarePlan = satu nomor; jangan menembak beberapa RS sekaligus.</li>
                            </ul>
                        </div>

                        {{-- ── DIAGRAM 2: jalur RJ/BPJS ── --}}
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">
                                Diagram 2 — Jalur RJ ke poli RS lain (BPJS): tanpa Task, tanpa persetujuan
                            </div>
                            <div class="overflow-x-auto">
                                <svg viewBox="0 0 800 280" style="min-width:720px; width:100%; font-family:inherit">
                                    {{-- Marker didefinisikan ulang di sini, tidak meminjam milik Diagram 1:
                                         id SVG memang berlaku se-dokumen, tapi meminjam bikin diagram ini
                                         diam-diam kehilangan panah kalau diagram sebelahnya dipindah/dihapus. --}}
                                    <defs>
                                        <marker id="panah-alur-bpjs" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7"
                                            markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--muted)" />
                                        </marker>
                                    </defs>
                                    <rect x="20" y="96" width="170" height="64" rx="12" fill="var(--surface-card)" />
                                    <text x="105" y="122" text-anchor="middle" font-size="12" font-weight="600" fill="var(--ink)">EMR RJ</text>
                                    <text x="105" y="140" text-anchor="middle" font-size="10" fill="var(--muted)">Tindak Lanjut = Rujuk</text>

                                    <rect x="290" y="96" width="200" height="64" rx="12" fill="var(--surface-card)" />
                                    <text x="390" y="122" text-anchor="middle" font-size="12" font-weight="600" fill="var(--ink)">BPJS vclaim-sisrute-rest</text>
                                    <text x="390" y="140" text-anchor="middle" font-size="10" fill="var(--muted)">orkestrator</text>

                                    <rect x="560" y="20" width="200" height="60" rx="12" fill="var(--surface-card)" />
                                    <text x="660" y="45" text-anchor="middle" font-size="12" font-weight="600" fill="var(--ink)">SATUSEHAT Rujukan</text>
                                    <text x="660" y="63" text-anchor="middle" font-size="10" fill="var(--muted)">kita TIDAK memanggil langsung</text>

                                    <rect x="290" y="196" width="200" height="60" rx="12" fill="var(--surface-card)" />
                                    <text x="390" y="220" text-anchor="middle" font-size="11" font-weight="600" fill="var(--ink)">rujukanKompetensi.hasil</text>
                                    <text x="390" y="238" text-anchor="middle" font-size="10" fill="var(--muted)">datadaftarpolirj_json (DB lokal)</text>

                                    <rect x="560" y="190" width="220" height="66" rx="12" fill="var(--surface-card)" />
                                    <text x="670" y="215" text-anchor="middle" font-size="11" font-weight="600" fill="var(--primary)">/rujukan/keluar</text>
                                    <text x="670" y="233" text-anchor="middle" font-size="10" fill="var(--muted)">tab Rawat Jalan (BPJS)</text>

                                    <line x1="190" y1="128" x2="284" y2="128" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-alur-bpjs)" />
                                    <text x="237" y="118" text-anchor="middle" font-size="10" fill="var(--muted)">Rujukan/Insert</text>

                                    <line x1="490" y1="112" x2="556" y2="66" stroke="var(--muted)" stroke-width="2" stroke-dasharray="6 4" marker-end="url(#panah-alur-bpjs)" />
                                    {{-- label diletakkan di bawah kotak SATUSEHAT, bukan di tengah panah miring:
                                         garis (490,112)→(556,66) persis melintasi titik tengahnya. --}}
                                    <text x="566" y="104" font-size="10" fill="var(--muted)">BPJS meneruskan</text>

                                    <line x1="390" y1="160" x2="390" y2="190" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-alur-bpjs)" />
                                    <text x="400" y="180" font-size="10" fill="var(--muted)">nomor terbit → disimpan</text>

                                    <line x1="490" y1="223" x2="554" y2="223" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-alur-bpjs)" />
                                    <text x="522" y="213" text-anchor="middle" font-size="10" fill="var(--muted)">dibaca layar</text>
                                </svg>
                            </div>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Kenapa dua diagram ini beda bentuk</div>
                            <p class="ds-body-md mb-3" style="max-width:66ch">
                                Diagram 1 punya lima anak panah bolak-balik karena ada <strong>persetujuan</strong>.
                                Diagram 2 lurus saja: BPJS yang mengorkestrasi, rujukan poli <strong>terbit seketika</strong>
                                begitu Insert berhasil. Tidak ada Task, tidak ada yang menyetujui, jadi tidak ada yang
                                perlu dipantau statusnya — yang membuktikan rujukan itu sah adalah <strong>nomornya</strong>.
                            </p>
                            <p class="ds-body-md" style="max-width:66ch">
                                Akibatnya layar <span class="ds-code">/rujukan/keluar</span> punya dua tab dengan kolom yang
                                berbeda: tab Ranap &amp; Gawat Darurat berkolom status jawaban (sumber API), tab Rawat Jalan
                                berkolom nomor rujukan (sumber DB lokal, sebab BPJS tak menyediakan endpoint
                                "daftar rujukan keluar saya"). Perbedaan itu memang begitu adanya, bukan tampilan yang
                                belum selesai.
                            </p>
                        </div>
                    </section>

                    {{-- ====== LOKASI PANEL ====== --}}
                    <section x-show="section === 'simrs-panel'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Di SIMRS</div>
                        <h1 class="ds-display-md mb-4">Lokasi Panel &amp; Node Data</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Panel</th><th>Muncul di</th><th>Node JSON</th><th>Komponen</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">RJ → poli RS lain (vclaim)</td><td class="ds-body-sm">EMR RJ → Perencanaan → Tindak Lanjut = <strong>Rujuk</strong></td><td class="ds-td-meta">rujukanKompetensi</td><td class="ds-td-meta">rm-rujukan-kompetensi-rj-actions</td></tr>
                                        <tr><td class="ds-td-strong">RJ → IGD/Ranap RS lain (FHIR)</td><td class="ds-body-sm">EMR RJ → Tindak Lanjut = Rujuk (di bawah panel vclaim)</td><td class="ds-td-meta">rujukanKompetensiFhir</td><td class="ds-td-meta">rm-rujukan-kompetensi-fhir-rj-actions</td></tr>
                                        <tr><td class="ds-td-strong">UGD → IGD/Ranap RS lain</td><td class="ds-body-sm">EMR UGD → Tindak Lanjut = <strong>Rujuk</strong> (bersanding form Rujukan Antar RS lama) — selector tujuan IGD|Ranap</td><td class="ds-td-meta">rujukanKompetensi</td><td class="ds-td-meta">rm-rujukan-kompetensi-ugd-actions</td></tr>
                                        <tr><td class="ds-td-strong">RI → Ranap RS lain</td><td class="ds-body-sm">EMR RI → Perencanaan → Tindak Lanjut = <strong>Pulang Pindah / Rujuk</strong></td><td class="ds-td-meta">rujukanKompetensi</td><td class="ds-td-meta">rm-rujukan-kompetensi-ri-actions</td></tr>
                                        <tr><td class="ds-td-strong">Rujukan MASUK (kita jadi tujuan)</td><td class="ds-body-sm">Menu <strong>Rujukan → Persetujuan Rujukan Masuk</strong> (<span class="ds-code">/rujukan/masuk</span>) — layar tersendiri, bukan di EMR</td><td class="ds-td-meta">— (murni API, tanpa tabel/node lokal)</td><td class="ds-td-meta">rujukan-masuk + rujukan-masuk-actions</td></tr>
                                        <tr><td class="ds-td-strong">Rujukan KELUAR (pemantauan jawaban)</td><td class="ds-body-sm">Menu <strong>Rujukan → Pemantauan Rujukan Keluar</strong> (<span class="ds-code">/rujukan/keluar</span>) — 2 tab, layar tersendiri</td><td class="ds-td-meta">tab Ranap/IGD: — (API). tab RJ: rujukanKompetensi.hasil</td><td class="ds-td-meta">rujukan-keluar + rujukan-keluar-actions</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Dua layar rujukan — penamaan memakai ARAH, bukan aktivitas</div>
                            <p class="ds-body-md mb-3" style="max-width:66ch">
                                Berkas &amp; route sengaja bernama <span class="ds-code">rujukan-masuk</span> dan
                                <span class="ds-code">rujukan-keluar</span> (bukan "persetujuan"/"pemantauan"),
                                supaya sisi mana yang dimaksud terbaca dari namanya sendiri. Keduanya di luar EMR:
                                rujukan masuk datang dari RS lain (belum ada pasiennya di sini), dan jawaban rujukan
                                keluar datang menyusul berjam-jam setelah pasien selesai ditangani.
                            </p>
                            <div class="ds-caption-up mb-2" style="color:var(--muted)">Kenapa layar KELUAR punya 2 tab</div>
                            <ul class="ds-body-md space-y-1 mb-3" style="max-width:66ch; list-style:disc; padding-left:20px">
                                <li><strong>Tab Ranap &amp; Gawat Darurat</strong> — sumber API SATUSEHAT
                                    (<span class="ds-code">rujukanTaskByRequester()</span>). Punya siklus persetujuan,
                                    jadi ada kolom status Menunggu / Disetujui / Ditolak.</li>
                                <li><strong>Tab Rawat Jalan (BPJS)</strong> — sumber <strong>DB lokal</strong>, karena
                                    BPJS tidak menyediakan endpoint "daftar rujukan keluar saya" dan jalur ini
                                    <strong>tidak membentuk Task FHIR</strong>. Rujukan poli terbit seketika begitu
                                    Insert berhasil, jadi tab ini <strong>tidak punya kolom status</strong> — yang
                                    ditonjolkan nomor rujukan BPJS &amp; SATUSEHAT. Perbedaan bentuk kedua tab ini
                                    nyata, bukan tampilan yang belum selesai.</li>
                            </ul>
                            <div class="ds-caption-up mb-2" style="color:var(--muted)">Jebakan kueri tab Rawat Jalan</div>
                            <ul class="ds-body-md space-y-1" style="max-width:66ch; list-style:disc; padding-left:20px">
                                <li>Filter <span class="ds-code">INSTR(datadaftarpolirj_json, '"rujukanKompetensi"')</span>
                                    wajib memakai <strong>kutip penutup</strong>. Tanpa itu ia ikut mencocoki
                                    <span class="ds-code">"rujukanKompetensiFhir"</span> — node rujukan RJ→IGD/Ranap
                                    yang sudah tampil di tab sebelah — sehingga rujukan yang sama muncul dobel.</li>
                                <li>Baris disaring pada <span class="ds-code">hasil.noRujukanSatuSehat</span> yang terisi.
                                    Panel EMR juga menyimpan <strong>draft</strong> (state form untuk retry) dengan
                                    <span class="ds-code">hasil = []</span>; draft bukan rujukan yang terbit.</li>
                                <li>Tab ini <strong>tidak dimuat saat halaman dibuka</strong> — kueri menyapu CLOB, jadi
                                    baru jalan setelah tabnya diklik. Rentang tanggal adalah penjaga performanya,
                                    bukan sekadar kenyamanan.</li>
                            </ul>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Perilaku bersama semua panel</div>
                            <ul class="ds-body-md space-y-1" style="max-width:66ch; list-style:disc; padding-left:20px">
                                <li>State form <strong>dipersist ke node JSON</strong> setiap langkah sukses — gangguan pusat tinggal retry tanpa isi ulang.</li>
                                <li>Semua payload + response mentah otomatis terekam di <span class="ds-code">web_log_status</span> — bukti wajib saat lapor Issue Tracker.</li>
                                <li>Error tampil sebagai <strong>toast + hint katalog</strong> (lihat FAQ); nomor rujukan tersimpan di DB + audit log.</li>
                                <li>Timeout ketat <span class="ds-code">timeout(8)->connectTimeout(3)</span> — outage pusat tidak memblokir EMR.</li>
                            </ul>
                        </div>
                    </section>

                    {{-- ====== FAQ ERROR ====== --}}
                    <section x-show="section === 'faq-error'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — FAQ</div>
                        <h1 class="ds-display-md mb-4">Katalog Error → Penanganan</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dirangkum dari kasus nyata puluhan RS di grup resmi (Apr–Agu 2026). Panel di SIMRS
                            menampilkan hint ini otomatis di pesan error.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Pesan / Gejala</th><th>Penyebab sebenarnya</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-meta">Unauthorized! You are not registered for this service!</td><td class="ds-body-sm">Cons-id belum didaftarkan untuk service SISRUTE</td><td class="ds-body-sm">Ajukan aktivasi ke BPJS — bukan bug aplikasi</td></tr>
                                        <tr><td class="ds-td-meta">Unauthorized! Consumer ID is expired!</td><td class="ds-body-sm">Masa berlaku cons-id habis / provisioning belum tuntas</td><td class="ds-body-sm">Ajukan reaktivasi/extend (form yang sama, keterangan "reaktivasi")</td></tr>
                                        <tr><td class="ds-td-meta">405 Method Not Allowed</td><td class="ds-body-sm">Verb salah — GetKriteriaRujukan harus POST ber-body JSON</td><td class="ds-body-sm">Sudah dibenahi di SisruteTrait (temuan live 11/08)</td></tr>
                                        <tr><td class="ds-td-meta">Index was out of range... (500)</td><td class="ds-body-sm">Mapping faskes BPJS↔SATUSEHAT belum ada, atau kodeFaskesSatuSehat salah (UUID/kosong)</td><td class="ds-body-sm">Verifikasi kode 9-digit production; lapor minta mapping</td></tr>
                                        <tr><td class="ds-td-meta">Response ... tidak mengandung Kriteria/Faskes Rujukan (500)</td><td class="ds-body-sm">Multi-penyebab: ICD-10 induk / wilayah belum termapping / org belum terdaftar / gangguan upstream</td><td class="ds-body-sm">Cek ICD-10 4-karakter dulu → retry; kalau serentak banyak faskes = gangguan pusat</td></tr>
                                        <tr><td class="ds-td-meta">Kandidat kosong tanpa error</td><td class="ds-body-sm">Memang tidak ada kandidat (diagnosa dinilai mampu ditangani sendiri)</td><td class="ds-body-sm">Bukan error — tampilkan info</td></tr>
                                        <tr><td class="ds-td-meta">linkId ... tidak valid, linkId valid: ...</td><td class="ds-body-sm">Kriteria basi / hardcode</td><td class="ds-body-sm">Muat ulang kriteria lalu pilih ulang</td></tr>
                                        <tr><td class="ds-td-meta">hanya boleh mengisi salah satu dari Terapi...</td><td class="ds-body-sm">Lebih dari satu kriteria terisi</td><td class="ds-body-sm">Tepat SATU kriteria (aturan Jul 2026)</td></tr>
                                        <tr><td class="ds-td-meta">PPK ... tidak ditemukan di pemetaan / Tujuan tidak sesuai PPK (400)</td><td class="ds-body-sm">Pasangan kode BPJS↔SATUSEHAT beda RS / RS belum termapping</td><td class="ds-body-sm">Pilih ulang dari kandidat; lapor mapping</td></tr>
                                        <tr><td class="ds-td-meta">Gagal mendapatkan nomor Rujukan Satu Sehat (400)</td><td class="ds-body-sm">Pusat gagal menerbitkan nomor — kambuhan Jul–Agu 2026</td><td class="ds-body-sm">Simpan bukti (otomatis di web_log_status), kirim ulang nanti; tak ada workaround klien</td></tr>
                                        <tr><td class="ds-td-meta">Value ... too large or too small for a Decimal (500)</td><td class="ds-body-sm">Bug sisi pusat (gel. 11/08/2026)</td><td class="ds-body-sm">Tunggu perbaikan BPJS/SATUSEHAT</td></tr>
                                        <tr><td class="ds-td-meta">noSep tidak ditemukan (400)</td><td class="ds-body-sm">SEP belum sinkron di server BPJS</td><td class="ds-body-sm">Cek SEP, coba lagi</td></tr>
                                        <tr><td class="ds-td-meta">Found duplicate: Task (20002)</td><td class="ds-body-sm">Task.identifier di-reuse</td><td class="ds-body-sm">UUID baru tiap POST (panel sudah otomatis)</td></tr>
                                        <tr><td class="ds-td-meta">429 Rate limit quota violation</td><td class="ds-body-sm">Kuota API staging habis</td><td class="ds-body-sm">Hemat panggilan; lapor minta perpanjang</td></tr>
                                        <tr><td class="ds-td-meta">Error IDENTIK di ≥2 endpoint berbeda</td><td class="ds-body-sm"><strong>Hampir pasti gangguan jaringan SATUSEHAT</strong></td><td class="ds-body-sm">JANGAN debug payload — tunggu, lalu retry (state tersimpan)</td></tr>
                                        <tr><td class="ds-td-meta">No consent available for CarePlan/... (HTTP 200)</td><td class="ds-body-sm"><strong>Cacat platform SATUSEHAT</strong> — consent bertipe CarePlan tidak pernah terbit untuk rujukan yang masuk ke kita</td><td class="ds-body-sm">Bukan bug kita &amp; tak ada workaround klien — lihat kotak di bawah</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Kotak masuk buta karena consent — sudah dibuktikan, bukan dugaan</div>
                            <p class="ds-body-md mb-3" style="max-width:66ch">
                                Rujukan masuk sering datang tanpa data klinis: <span class="ds-code">_include=Task:based-on</span>
                                membalas OperationOutcome <span class="ds-code">No consent available for CarePlan/&lt;id&gt;</span>,
                                HTTP tetap 200. Karena nama pasien, layanan, jalur, dan keterangan klinis
                                <strong>hanya ada di CarePlan</strong> — tidak pernah di Task — kolom-kolom itu kosong berjamaah.
                            </p>
                            <ul class="ds-body-md space-y-1 mb-3" style="max-width:66ch; list-style:disc; padding-left:20px">
                                <li><strong>Tidak bisa diakali dari sumber lain.</strong> <span class="ds-code">Patient/&lt;ihs&gt;</span>
                                    terbaca 200 tapi cuma cangkang (<span class="ds-code">active, id, identifier, meta</span>) —
                                    tanpa nama, tanpa gender, tanpa tanggal lahir, NIK ikut di-mask. Encounter &amp; Condition
                                    rujukan itu sama-sama disensor.</li>
                                <li><strong>Consent-nya memang tidak ada.</strong> Pada kasus 17/08/2026 pasien punya 718 Consent,
                                    hanya 2 bertipe CarePlan — keduanya menunjuk CarePlan lain, untuk organisasi lain, dan sudah
                                    kedaluwarsa. Consent diterbitkan otomatis oleh platform, bukan oleh pengirim.</li>
                                <li><strong>Awas saat memeriksa sendiri:</strong> <span class="ds-code">Bundle.total</span> pada
                                    hasil pencarian Consent = ukuran halaman, bukan jumlah sebenarnya. Wajib ikuti
                                    <span class="ds-code">link.next</span>.</li>
                            </ul>
                            <p class="ds-body-md" style="max-width:66ch">
                                <strong>Yang dilakukan layar kotak masuk:</strong> CarePlan tersensor → baris tetap tampil,
                                ditandai "tersembunyi — consent belum ada" plus peringatan bahwa keputusan diambil tanpa data
                                klinis. Task tersensor <strong>tidak menyisakan baris sama sekali</strong>, jadi jumlahnya
                                dimunculkan sebagai spanduk — tanpa itu, permintaan yang hilang tak akan diketahui siapa pun
                                padahal perujuk menunggu. Kalimatnya sengaja dibedakan dari "perujuk tidak mengisi", karena
                                tindak lanjutnya beda.
                            </p>
                        </div>
                    </section>

                    {{-- ====== FAQ UMUM ====== --}}
                    <section x-show="section === 'faq-umum'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — FAQ</div>
                        <h1 class="ds-display-md mb-4">Pertanyaan Umum</h1>
                        <div class="space-y-4">
                            @foreach ([
                                ['Kenapa harus pilih dari kandidat? Tidak bisa tunjuk RS langsung?', 'Itu inti SRBK: kandidat dihitung engine pusat dari diagnosa + kriteria + wilayah + kompetensi RS. Insert memang tidak memvalidasi, tapi menembak RS di luar kandidat menyalahi alur — SIMRS kita sengaja mengunci pilihan ke daftar kandidat.'],
                                ['Pasien poli butuh dirawat inap di RS lain — pakai panel yang mana?', 'Panel "IGD/Ranap RS Lain (SATUSEHAT FHIR)" di tab Tindak Lanjut EMR RJ, pilih tujuan Rawat Inap. BUKAN panel vclaim (itu khusus tujuan poli).'],
                                ['Pasien pindah RJ→UGD atau UGD→RI di RS kita sendiri?', 'Bukan SRBK. Alur internal biasa: pendaftaran episode baru / modul Transfer ke RI. Tidak ada rujukan yang dikirim ke mana pun.'],
                                ['Kenapa kandidat kosong terus padahal payload benar?', 'Tiga kemungkinan: (1) diagnosa dinilai mampu ditangani sendiri — memang tidak diberi kandidat; (2) wilayah belum termapping di pusat; (3) gangguan upstream. Coba variasikan tindakan ICD-9 pada kriteria — pilihan tindakan menentukan match kompetensi.'],
                                ['RS kami tidak muncul sebagai kandidat di RS lain?', 'Kandidat ditentukan engine (wilayah + kompetensi + jarak). Tidak bisa "memaksa" muncul — pastikan data kompetensi/SISRUTE RS terdaftar; kasus serupa (RSIA) diinvestigasi pusat. Kebijakan resmi: semua RS diperlakukan sebagai RS umum, diurutkan jarak & waktu tempuh.'],
                                ['Approval faskes tujuan wajib ditunggu?', 'Produksi: penerima merespons (accepted/rejected) via Task; ±15 menit tanpa respons → pindah kandidat. Staging: approval boleh dilewati, langsung ServiceRequest setelah bundle terkirim.'],
                                ['Nomor rujukan harus tampil di UI?', 'Tidak wajib tampil — yang WAJIB: tersimpan di database (syarat UAT). Panel kita menampilkan sekaligus menyimpannya di node JSON + audit log.'],
                                ['Kirim gagal karena gangguan pusat — isian hilang?', 'Tidak. State form dipersist ke node JSON tiap langkah; buka lagi kapan pun, tinggal klik ulang tombol terakhir. Identifier baru dibuat otomatis tiap retry.'],
                                ['Bagaimana lapor kendala ke BPJS/Kemkes?', 'Sertakan payload + response mentah (otomatis terekam di web_log_status), kode faskes, cons-id, dan waktu kejadian. Kendala dicatat di Issue Tracker resmi; error identik massal cukup pantau grup.'],
                            ] as [$q, $a])
                                <div class="ds-card-outline" style="padding:16px 20px">
                                    <div class="ds-title-sm mb-1" style="color:var(--ink)">{{ $q }}</div>
                                    <p class="ds-body-sm" style="color:var(--body); max-width:70ch">{{ $a }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- ====== REFERENSI ====== --}}
                    <section x-show="section === 'referensi'" x-cloak>
                        <div class="ds-eyebrow mb-3">14 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Dokumen &amp; Sumber</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Sumber</th><th>Isi</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">docs/rujukan-kompetensi.md</td><td class="ds-body-sm">Catatan lapangan lengkap + katalog error penuh (hasil studi 13.6k baris chat grup)</td></tr>
                                        <tr><td class="ds-td-strong">Skill repo <span class="ds-code">rujukan-kompetensi</span></td><td class="ds-body-sm">Model integrasi + aturan payload — WAJIB dibaca sebelum mengubah kode modul ini</td></tr>
                                        <tr><td class="ds-td-strong">Postman "30. Use Case - Rujukan Pasien V30062026"</td><td class="ds-body-sm">Contoh payload resmi RJ/RI/Darurat (folder export chat WA)</td></tr>
                                        <tr><td class="ds-td-strong">Playbook Rujukan Pasien (PDF)</td><td class="ds-body-sm">RJ, RI, dan Rawat Darurat + Lampiran 4 Kelompok Layanan</td></tr>
                                        <tr><td class="ds-td-strong">Skenario UAT SRBK (FKTL) v1.0</td><td class="ds-body-sm">Skenario uji resmi; hasil UAT di-upload ke portal Kemkes</td></tr>
                                        <tr><td class="ds-td-strong">Trait kode</td><td class="ds-body-sm"><span class="ds-code">app/Http/Traits/BPJS/SisruteTrait.php</span> &amp; <span class="ds-code">app/Http/Traits/SATUSEHAT/SatuSehatRujukanTrait.php</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted)">
                            Wilayah piloting: Kota Bandung, Kota Makassar, <strong>Kab. Tulungagung</strong>,
                            Kab. Muara Enim. Env dev: faskes 0184R006 MADINAH (JST), CID SISRUTE 8334.
                        </p>
                    </section>

                    {{-- ============ NAV BAWAH ============ --}}
                    <div class="mt-12 flex items-center justify-between" style="border-top:1px solid var(--hairline); padding-top:20px">
                        <button type="button" x-show="idx() > 0" x-on:click="go(order[idx() - 1])"
                            class="ds-btn ds-btn-secondary">← <span x-text="labels[order[idx() - 1]]"></span></button>
                        <span x-show="idx() <= 0"></span>
                        <button type="button" x-show="idx() < order.length - 1" x-on:click="go(order[idx() + 1])"
                            class="ds-btn ds-btn-primary"><span x-text="labels[order[idx() + 1]]"></span> →</button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
