<?php

use Livewire\Component;

// Tutorial ALUR PELAYANAN pasien di SIMRS — pendaftaran → pelayanan/EMR →
// apotek → kasir, diklasifikasikan per jalur RJ / UGD / RI.
// Beda dari koding-administrasi (fokus kode/tabel): halaman ini menjelaskan
// URUTAN OPERASIONAL — siapa membuka menu apa, status apa yang berubah,
// dan stempel antrean BPJS (taskId) mana yang tercatat di tiap tahap.
// Gaya sidebar per-seksi sama dgn koding-satusehat.
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
                'peta-modul' => 'Peta Modul & Role',
            ],
            'Rawat Jalan' => [
                'rj-daftar' => '1. Daftar RJ (Pendaftaran)',
                'rj-pelayanan' => '2. Pelayanan RJ (EMR)',
                'rj-apotek' => '3. Apotek RJ',
                'rj-kasir' => '4. Kasir RJ',
                'rj-status' => 'Siklus Status & taskId',
            ],
            'Cabang dari EMR' => [
                'rj-laborat' => 'Order Lab → Modul Laborat',
                'rj-radiologi' => 'Order Rad → Modul Radiologi',
                'rj-eresep' => 'E-Resep → Apotek',
            ],
            'Jalur Lain' => [
                'ugd' => 'UGD (menyusul)',
                'ri' => 'Rawat Inap (menyusul)',
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
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('panduan-dev') }}" wire:navigate class="ds-body-sm hover:underline"
                        style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Alur Pelayanan</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('panduan-dev.koding-administrasi') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">←
                        Tutorial Administrasi</a>
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
                            Pendamping: <a href="{{ route('panduan-dev.koding-administrasi') }}" wire:navigate
                                class="hover:underline" style="color:var(--primary)">Tutorial Koding Administrasi</a><br>
                            Ruang lingkup aktif: <span class="ds-code">Rawat Jalan</span><br>
                            UGD &amp; RI menyusul di halaman ini juga.
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    {{-- ====== PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Alur Pelayanan Pasien di SIMRS</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Tutorial ini memetakan <strong>perjalanan satu pasien</strong> di dalam sistem —
                            dari didaftarkan, dilayani dokter (EMR), mengambil obat, sampai membayar di kasir —
                            beserta <strong>menu yang dipakai, role yang bertanggung jawab, dan status yang
                            berubah</strong> di tiap tahap. Alur diklasifikasikan per jalur:
                            <strong>Rawat Jalan (RJ)</strong>, <strong>UGD</strong>, dan <strong>Rawat Inap (RI)</strong>.
                        </p>

                        {{-- Diagram tahap RJ --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-4" style="color:var(--muted)">Empat tahap Rawat Jalan</div>
                            <div class="flex flex-wrap items-stretch gap-2">
                                @foreach ([
                                    ['1', 'Daftar RJ', 'Pendaftaran & SEP', 'rj-daftar'],
                                    ['2', 'Pelayanan RJ', 'EMR dokter/perawat', 'rj-pelayanan'],
                                    ['3', 'Apotek RJ', 'Telaah & serah obat', 'rj-apotek'],
                                    ['4', 'Kasir RJ', 'Administrasi & bayar', 'rj-kasir'],
                                ] as [$no, $judul, $sub, $target])
                                    <button type="button" x-on:click="go('{{ $target }}')"
                                        class="flex-1 min-w-[150px] text-left rounded-xl p-4 transition hover:shadow-md"
                                        style="background:var(--surface-card)">
                                        <div class="ds-caption-up" style="color:var(--primary)">Tahap {{ $no }}</div>
                                        <div class="ds-title-md" style="color:var(--ink)">{{ $judul }}</div>
                                        <div class="ds-body-sm" style="color:var(--muted)">{{ $sub }}</div>
                                    </button>
                                    @if (!$loop->last)
                                        <div class="self-center ds-title-md" style="color:var(--muted-soft)">→</div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Peta besar: alur pasien (garis penuh) + aliran biaya (putus-putus) --}}
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-2" style="color:var(--muted)">Peta besar satu kunjungan RJ</div>
                            <div class="overflow-x-auto">
                                <svg viewBox="0 0 860 396" style="min-width:700px; width:100%; font-family:inherit">
                                    <defs>
                                        <marker id="panah" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7"
                                            markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--muted)" />
                                        </marker>
                                        <marker id="panah-biaya" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7"
                                            markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--primary)" />
                                        </marker>
                                    </defs>

                                    {{-- ── Baris atas: alur pasien ── --}}
                                    <rect x="20" y="40" width="150" height="64" rx="12" fill="var(--surface-card)" />
                                    <text x="95" y="66" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 1</text>
                                    <text x="95" y="86" text-anchor="middle" font-size="14" font-weight="600"
                                        fill="var(--ink)">Daftar RJ</text>

                                    <rect x="250" y="40" width="190" height="64" rx="12" fill="var(--surface-card)" />
                                    <text x="345" y="66" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 2</text>
                                    <text x="345" y="86" text-anchor="middle" font-size="14" font-weight="600"
                                        fill="var(--ink)">Pelayanan RJ (EMR)</text>

                                    <rect x="690" y="40" width="150" height="64" rx="12" fill="var(--surface-card)" />
                                    <text x="765" y="66" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 4</text>
                                    <text x="765" y="86" text-anchor="middle" font-size="14" font-weight="600"
                                        fill="var(--ink)">Kasir RJ</text>

                                    <line x1="170" y1="72" x2="244" y2="72" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <line x1="440" y1="72" x2="684" y2="72" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="562" y="62" text-anchor="middle" font-size="11" fill="var(--muted)">selesai
                                        dilayani</text>

                                    {{-- ── Cabang dari EMR ── --}}
                                    <rect x="120" y="220" width="150" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="195" y="244" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Modul Laborat</text>
                                    <text x="195" y="262" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">P → C → H</text>

                                    <rect x="310" y="220" width="160" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="390" y="244" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Modul Radiologi</text>
                                    <text x="390" y="262" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">foto + bacaan</text>

                                    <rect x="510" y="220" width="180" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="600" y="238" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 3</text>
                                    <text x="600" y="256" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Apotek RJ</text>
                                    <text x="600" y="270" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">e-resep → serah obat</text>

                                    <line x1="300" y1="104" x2="215" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="230" y="160" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">order lab</text>
                                    <line x1="360" y1="104" x2="388" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="402" y="160" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">order rad</text>
                                    <line x1="425" y1="104" x2="580" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="540" y="150" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">e-resep</text>

                                    {{-- ── Aliran biaya (putus-putus) → pos biaya → kasir ── --}}
                                    <rect x="690" y="320" width="150" height="48" rx="24" fill="none"
                                        stroke="var(--primary)" stroke-width="1.5" />
                                    <text x="765" y="340" text-anchor="middle" font-size="12" font-weight="600"
                                        fill="var(--primary)">Pos Biaya</text>
                                    <text x="765" y="356" text-anchor="middle" font-size="11"
                                        fill="var(--primary)">kunjungan</text>

                                    <line x1="195" y1="276" x2="195" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <line x1="195" y1="344" x2="684" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" marker-end="url(#panah-biaya)" />
                                    <line x1="390" y1="276" x2="390" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <line x1="600" y1="276" x2="600" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <line x1="765" y1="320" x2="765" y2="110" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" marker-end="url(#panah-biaya)" />
                                    <text x="782" y="215" font-size="11" fill="var(--primary)"
                                        transform="rotate(90 782 215)" text-anchor="middle">dibayar di kasir</text>
                                </svg>
                            </div>
                            <div class="flex flex-wrap gap-4 mt-2">
                                <span class="ds-body-sm inline-flex items-center gap-2" style="color:var(--muted)">
                                    <span style="display:inline-block;width:26px;border-top:2px solid var(--muted)"></span>
                                    alur pasien / dokumen
                                </span>
                                <span class="ds-body-sm inline-flex items-center gap-2" style="color:var(--muted)">
                                    <span style="display:inline-block;width:26px;border-top:2px dashed var(--primary)"></span>
                                    aliran biaya (otomatis)
                                </span>
                            </div>
                        </div>

                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Dua "rel" berjalan sejajar di sepanjang alur:
                        </p>
                        <ul class="ds-body-md mb-6 space-y-2" style="max-width:62ch; list-style:disc; padding-left:1.2em">
                            <li><strong>Rel klinis</strong> — EMR (screening, anamnesis, pemeriksaan, diagnosa,
                                e-resep, order penunjang). Hasilnya tersimpan sebagai JSON kunjungan
                                (<span class="ds-code">datadaftarpolirj_json</span>).</li>
                            <li><strong>Rel administrasi</strong> — pos biaya (poli, jasa, obat, lab, radiologi, …)
                                yang terkumpul otomatis dari tindakan klinis, lalu dibayar di kasir.</li>
                        </ul>
                        <p class="ds-body-md" style="max-width:62ch">
                            Untuk pasien BPJS ada rel ketiga: <strong>stempel antrean BPJS (taskId 1–7)</strong> —
                            waktu tiap tahap dilaporkan ke BPJS untuk menghitung waktu tunggu. Ringkasannya ada di
                            seksi <button type="button" class="hover:underline" style="color:var(--primary)"
                                x-on:click="go('rj-status')">Siklus Status &amp; taskId</button>.
                        </p>
                    </section>

                    {{-- ====== PETA MODUL ====== --}}
                    <section x-show="section === 'peta-modul'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Peta Modul &amp; Role (Rawat Jalan)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Satu tahap = satu menu utama. Role menentukan menu mana yang tampil di APP
                            (diatur di <span class="ds-code">app/Services/AppMenu.php</span>).
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Tahap</th>
                                            <th>Menu (URL)</th>
                                            <th>Role</th>
                                            <th>Fungsi utama</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['1. Pendaftaran', 'Daftar Rawat Jalan — /rawat-jalan/daftar', 'Mr, Admin, Supervisor Tu, Manager Umum', 'Daftarkan pasien ke poli, klaim & SEP, antrean'],
                                            ['1b. Booking', 'Booking Rawat Jalan — /rawat-jalan/booking', 'Mr, Admin, Supervisor Tu, Manager Umum', 'Aktivasi booking Mobile JKN jadi pendaftaran'],
                                            ['2. Pelayanan', 'Pelayanan Rawat Jalan — /rawat-jalan/pelayanan', 'Dokter, Perawat, Admin, Manager Medis', 'EMR poli: pengkajian s/d resep'],
                                            ['3. Apotek', 'Antrian Apotek RJ — /transaksi/rj/antrian-apotek-rj (atau tab RJ di /transaksi/apotek)', 'Apoteker, Admin, Manager Medis', 'Telaah resep/obat, serah obat'],
                                            ['4. Kasir', 'Antrian Kasir RJ — /transaksi/rj/antrian-kasir-rj (atau tab RJ di /transaksi/kasir)', 'Tu, Admin, Supervisor Tu, Manager Umum', 'Administrasi biaya & pembayaran'],
                                            ['Pendukung', 'Jadwal Kontrol Pasien — /jadwal-kontrol', 'Mr, Admin, Tu, Supervisor Tu, Manager Umum', 'Geser tanggal SKDP + update BPJS'],
                                            ['Pendukung', 'Daftar Pasien Bulanan RJ — /rawat-jalan/daftar-bulanan', 'Casemix dkk.', 'Rekap bulanan, berkas klaim'],
                                        ] as [$tahap, $menu, $role, $fungsi])
                                            <tr>
                                                <td class="ds-td-token">{{ $tahap }}</td>
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
                                Satu kunjungan RJ = satu baris <span class="ds-code">rstxn_rjhdrs</span>
                                (kunci <span class="ds-code">rj_no</span>). Semua isi EMR + SEP + taskId +
                                e-resep hidup di satu kolom JSON <span class="ds-code">datadaftarpolirj_json</span> —
                                setiap tahap membaca &amp; menambal JSON yang sama.
                            </span>
                        </div>
                    </section>

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

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Yang terjadi di sistem: baris <span class="ds-code">rstxn_rjhdrs</span> dibuat dengan
                                <span class="ds-code">rj_status = 'A'</span> (Antrian) — dan untuk pasien BPJS,
                                stempel antrean <strong>taskId 1</strong> (admisi), <strong>2</strong> (dipanggil),
                                <strong>3</strong> (daftar poli) terkirim ke BPJS.
                            </span>
                        </div>
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

                        <p class="ds-body-md" style="max-width:62ch">
                            Tiga hal yang <em>dikirim</em> dari EMR berlanjut ke program lain dan dikerjakan
                            petugas berbeda:
                            <button type="button" class="hover:underline" style="color:var(--primary)"
                                x-on:click="go('rj-laborat')">Order Lab → Modul Laborat</button> ·
                            <button type="button" class="hover:underline" style="color:var(--primary)"
                                x-on:click="go('rj-radiologi')">Order Rad → Modul Radiologi</button> ·
                            <button type="button" class="hover:underline" style="color:var(--primary)"
                                x-on:click="go('rj-eresep')">E-Resep → Apotek</button>.
                        </p>
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

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Stempel taskId idempoten — diset hanya bila kosong dan dikirim ulang hanya bila
                                BPJS belum menjawab 200/208, jadi klik dobel tidak menggeser waktu tunggu.
                                Nomor antrean apotek juga dilaporkan terpisah ke BPJS
                                (<span class="ds-code">tambahAntrianApotek</span>).
                            </span>
                        </div>
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

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Setelah lunas, layar administrasi terkunci untuk perubahan biaya — pembukaan
                                kembali hanya oleh role tertentu (Admin/Tu). Rincian model datanya ada di
                                <a href="{{ route('panduan-dev.koding-administrasi') }}" wire:navigate
                                    class="hover:underline" style="color:var(--primary)">Tutorial Koding
                                    Administrasi</a>.
                            </span>
                        </div>
                    </section>

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

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Rel administrasinya: biaya lab menempel otomatis ke kunjungan induk, dan tab
                                Laboratorium di layar Administrasi bersifat <strong>read-only</strong> —
                                kelola/batal hanya dari modul lab (batal = eskalasi Admin/Supervisor
                                Penunjang, bukan petugas lab sendiri).
                            </span>
                        </div>
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

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Pembatalan order radiologi dari modul ini ikut menghapus biayanya di kunjungan
                                induk dan tercatat di audit log kunjungan — sama disiplinnya dengan lab.
                            </span>
                        </div>
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

                    {{-- ====== UGD (PLACEHOLDER) ====== --}}
                    <section x-show="section === 'ugd'" x-cloak>
                        <div class="ds-eyebrow mb-3">Jalur Lain</div>
                        <h1 class="ds-display-md mb-4">UGD — menyusul</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Seksi UGD akan mengikuti kerangka yang sama (Daftar → Pelayanan/EMR → Apotek →
                            Kasir). Perbedaan pokok yang akan dibahas:
                        </p>
                        <ul class="ds-body-md space-y-2" style="max-width:62ch; list-style:disc; padding-left:1.2em">
                            <li><strong>Triase</strong> P0–P3 di awal pelayanan (tidak ada di RJ).</li>
                            <li>Pendaftaran &amp; pelayanan menyatu — pasien datang tanpa booking/poli.</li>
                            <li><strong>Transfer</strong> ke Rawat Inap (form dua sisi, penjaminan kamar) atau pulang.</li>
                            <li>Antrian Apotek UGD &amp; Kasir UGD terpisah dari RJ.</li>
                        </ul>
                    </section>

                    {{-- ====== RI (PLACEHOLDER) ====== --}}
                    <section x-show="section === 'ri'" x-cloak>
                        <div class="ds-eyebrow mb-3">Jalur Lain</div>
                        <h1 class="ds-display-md mb-4">Rawat Inap — menyusul</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Seksi RI akan membahas alur menginap yang lebih panjang:
                        </p>
                        <ul class="ds-body-md space-y-2" style="max-width:62ch; list-style:disc; padding-left:1.2em">
                            <li>Pendaftaran RI (cara masuk: rujukan poli, UGD, langsung) + SEP RI/SPRI.</li>
                            <li>EMR RI harian: CPPT, SBAR, asuhan keperawatan, penilaian, gizi, visite.</li>
                            <li>Apotek RI per-lembar resep + administrasi obat + kasir apotek RI.</li>
                            <li>Administrasi RI (kamar, visite, konsul, paket) → pasien pulang → kasir RI.</li>
                        </ul>
                    </section>

                    {{-- ============ PAGER ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-12 pt-6"
                        style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-secondary" x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">← <span x-text="labels[order[idx() - 1]]"></span></button>
                        <span></span>
                        <button type="button" class="ds-btn ds-btn-primary" x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])"><span x-text="labels[order[idx() + 1]]"></span> →</button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
