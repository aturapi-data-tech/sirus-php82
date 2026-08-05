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
                'master-pasien' => 'Master Pasien (Identitas)',
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
                'ok' => 'Kamar Operasi (OK)',
            ],
            'UGD' => [
                'ugd-daftar' => '1. Daftar UGD',
                'ugd-pelayanan' => '2. Pelayanan UGD (EMR)',
                'ugd-apotek' => '3. Apotek UGD',
                'ugd-kasir' => '4. Kasir UGD',
            ],
            'Rawat Inap' => [
                'ri-daftar' => '1. Masuk RI (Pendaftaran)',
                'ri-emr' => '2. EMR RI Harian',
                'ri-apotek' => '3. Apotek RI (per lembar)',
                'ri-administrasi' => '4. Administrasi & Pulang',
                'ri-kasir' => '5. Kasir RI',
            ],
            'Gudang' => [
                'gudang-penerimaan' => 'Penerimaan Medis & Non-Medis',
                'gudang-transfer' => 'Transfer Stok (Distribusi)',
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

                                    {{-- ── Cabang dari EMR (4 cabang) ── --}}
                                    <rect x="45" y="220" width="145" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="117" y="244" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Modul Laborat</text>
                                    <text x="117" y="262" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">P → C → H</text>

                                    <rect x="205" y="220" width="155" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="282" y="244" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Modul Radiologi</text>
                                    <text x="282" y="262" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">foto + bacaan</text>

                                    <rect x="375" y="220" width="150" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="450" y="244" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Kamar Operasi</text>
                                    <text x="450" y="262" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">OK — A → L</text>

                                    <rect x="540" y="220" width="170" height="56" rx="12" fill="var(--surface-card)" />
                                    <text x="625" y="238" text-anchor="middle" font-size="12" fill="var(--primary)"
                                        font-weight="700">TAHAP 3</text>
                                    <text x="625" y="256" text-anchor="middle" font-size="13" font-weight="600"
                                        fill="var(--ink)">Apotek RJ</text>
                                    <text x="625" y="270" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">e-resep → serah obat</text>

                                    <line x1="285" y1="104" x2="135" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="180" y="150" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">order lab</text>
                                    <line x1="330" y1="104" x2="285" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="290" y="165" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">order rad</text>
                                    <line x1="380" y1="104" x2="445" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="432" y="150" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">kirim OK</text>
                                    <line x1="430" y1="104" x2="605" y2="214" stroke="var(--muted)" stroke-width="2"
                                        marker-end="url(#panah)" />
                                    <text x="555" y="140" text-anchor="middle" font-size="11"
                                        fill="var(--muted)">e-resep</text>

                                    {{-- ── Aliran biaya (putus-putus) → pos biaya → kasir ── --}}
                                    <rect x="690" y="320" width="150" height="48" rx="24" fill="none"
                                        stroke="var(--primary)" stroke-width="1.5" />
                                    <text x="765" y="340" text-anchor="middle" font-size="12" font-weight="600"
                                        fill="var(--primary)">Pos Biaya</text>
                                    <text x="765" y="356" text-anchor="middle" font-size="11"
                                        fill="var(--primary)">kunjungan</text>

                                    <line x1="117" y1="276" x2="117" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <line x1="117" y1="344" x2="684" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" marker-end="url(#panah-biaya)" />
                                    <line x1="282" y1="276" x2="282" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <line x1="450" y1="276" x2="450" y2="344" stroke="var(--primary)" stroke-width="1.5"
                                        stroke-dasharray="5 4" />
                                    <text x="462" y="316" font-size="10" fill="var(--primary)">Trf Biaya</text>
                                    <line x1="625" y1="276" x2="625" y2="344" stroke="var(--primary)" stroke-width="1.5"
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

                    {{-- ====== MASTER PASIEN ====== --}}
                    <section x-show="section === 'master-pasien'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Master Pasien — Fondasi Identitas</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Sebelum alur mana pun berjalan, pasien harus punya <strong>satu No. RM</strong> di
                            Master Pasien (<span class="ds-code">/master/pasien</span>) — sumber identitas
                            tunggal yang dipakai pendaftaran RJ, UGD, dan RI. Dari sini pula tombol pintas ke
                            tiga pendaftaran tersedia, dan LOV "Cari Pasien" di semua form membaca data ini.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => null, 'judul' => 'Pasien baru?', 'sub' => 'cari dulu — cegah RM ganda'],
                                ['chip' => 'RM', 'judul' => 'Tambah Data Pasien', 'sub' => 'identitas + NIK + BPJS', 'chipWarna' => 'sky'],
                                ['chip' => 'IHS', 'judul' => 'Patient UUID', 'sub' => 'ID SATUSEHAT per pasien', 'chipWarna' => 'green'],
                                ['chip' => null, 'judul' => 'Siap didaftarkan', 'sub' => 'RJ · UGD · RI'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tampilan program</div>
                        @include('pages.panduan-dev.alur-pelayanan.partial-galeri', ['gambarList' => [
                            ['src' => 'images/panduan-dev/alur/master-pasien/01-list.png', 'caption' => 'Master Pasien — ±133 ribu pasien; cari nama/NRM/NIK, tombol pintas Pendaftaran Rawat Jalan / UGD / Rawat Inap, Tambah Data Pasien Baru, dan Edit/Hapus per baris.'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/02-data-pasien.png', 'caption' => 'Tab Data Pasien — data dasar (nama + gelar, tempat/tanggal lahir dengan umur auto), data sosial (JK, agama, pendidikan, pekerjaan), dan data budaya; toggle "Pasien Tidak Dikenal" untuk pasien tak sadar.'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/03-identitas-alamat.png', 'caption' => 'Tab Identitas & Alamat — Patient UUID (IHS SATUSEHAT, bisa di-generate ulang), NIK, ID BPJS, paspor; alamat KTP vs domisili dengan LOV desa berkode wilayah (desa → kecamatan → kab → provinsi).'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/04-kontak-keluarga.png', 'caption' => 'Tab Kontak & Keluarga — No. HP pasien + penanggung jawab (nama, HP, hubungan) + data ayah/ibu; penting untuk consent & penjamin.'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/05-rekam-medis.png', 'caption' => 'Tab Rekam Medis — riwayat kunjungan pasien lintas jalur langsung dari master (poli/dokter, diagnosis, terapi, Copy Resep / Resume Medis).'],
                            ['src' => 'images/panduan-dev/alur/master-pasien/06-status-kunci.png', 'caption' => 'Tab Status Kunci — lockstatus menandai pasien sedang aktif di satu jalur (cegah daftar ganda UGD/RJ/RI); otomatis lepas saat pulang, tombol Reset hanya untuk yang nyangkut.'],
                        ]])
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

                    {{-- ====== CABANG — KAMAR OPERASI ====== --}}
                    <section x-show="section === 'ok'" x-cloak>
                        <div class="ds-eyebrow mb-3">Cabang dari EMR</div>
                        <h1 class="ds-display-md mb-4">Kamar Operasi (OK)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Cabang penunjang keempat — melayani pasien <strong>RJ, UGD, maupun RI</strong>
                            (transaksi OK menempel ke kunjungan induk lewat jalur + nomor kunjungannya).
                            Dua menu terlibat: <strong>Jadwal Operasi</strong>
                            (<span class="ds-code">/operasi/jadwal-operasi</span> — booking) dan
                            <strong>Transaksi Kamar Operasi</strong>
                            (<span class="ds-code">/transaksi/penunjang/kamar-operasi</span> — pelaksanaan
                            &amp; biaya; role petugas OK / Supervisor Penunjang).
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'booking', 'judul' => 'Jadwal Operasi', 'sub' => 'rencana operasi pasien'],
                                ['chip' => 'A', 'judul' => 'Transaksi OK dibuka', 'sub' => 'pasien masuk kamar operasi', 'chipWarna' => 'amber'],
                                ['chip' => null, 'judul' => 'Tindakan & tarif', 'sub' => 'jenis operasi · kelas'],
                                ['chip' => null, 'judul' => 'Crew & jasa', 'sub' => 'operator · asisten · omloop · oncall'],
                                ['chip' => null, 'judul' => 'Bahan & alat', 'sub' => 'pemakaian BHP'],
                                ['chip' => 'L', 'judul' => 'Trf Biaya → induk', 'sub' => 'pos Operasi (OK) kunjungan', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Status transaksi OK</div>
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
                                            ['A', 'Berjalan', 'Tindakan/crew/bahan masih bisa diisi & diubah'],
                                            ['L', 'Selesai — biaya sudah ditransfer ke kunjungan induk', 'Terkunci; pembatalan transfer (kembali ke A) hanya oleh role berwenang'],
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
                                Kaitannya dengan alur pasien: tombol <strong>Trf Biaya</strong> itulah yang
                                mengisi pos <strong>Kamar Operasi / Operasi (OK)</strong> di administrasi
                                RJ/UGD/RI. Khusus RI, transfer mensyaratkan pasien <strong>masih
                                dirawat</strong> — karena itu pemulangan ditahan sistem selama masih ada
                                transaksi OK berstatus A (lihat guard pulang di seksi Rawat Inap).
                            </span>
                        </div>
                    </section>

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

                    {{-- ====== RI 1 — MASUK ====== --}}
                    <section x-show="section === 'ri-daftar'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 1</div>
                        <h1 class="ds-display-md mb-4">Masuk RI (Pendaftaran)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Alur RI beda watak dari RJ/UGD: bukan satu kali lewat, melainkan
                            <strong>berhari-hari dan berulang</strong>. Semua berawal di
                            <strong>Daftar Rawat Inap</strong> (<span class="ds-code">/ri/daftar</span>) —
                            halaman pusat kerja RI yang diakses hampir semua role klinis.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => null, 'judul' => 'Asal pasien', 'sub' => 'transfer UGD · rujukan poli · langsung'],
                                ['chip' => 'Mr/Tu', 'judul' => 'Pendaftaran RI', 'sub' => 'cara masuk · dokter penerima', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Tempati kamar', 'sub' => 'bangsal · kamar (bed internal)'],
                                ['chip' => 'BPJS', 'judul' => 'SEP RI + SPRI', 'sub' => 'DPJP sinkron', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Tiga pintu masuk</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-md space-y-3" style="list-style:disc; padding-left:1.2em">
                                <li><strong>Transfer dari UGD</strong> — paling umum. Modal transfer dua sisi:
                                    perawat UGD mengirim (pilih kamar + penjaminan), perawat ruangan menerima;
                                    biaya UGD ikut terbawa.</li>
                                <li><strong>Rujukan poli (RJ)</strong> — dokter poli memutuskan rawat inap.</li>
                                <li><strong>Daftar langsung</strong> — pasien datang membawa surat/rencana MRS.</li>
                            </ul>
                            <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">
                                Cara masuk tercatat dari master cara masuk (rujukan / UGD / langsung) — dipakai
                                pelaporan & klaim.
                            </p>
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Menu pendukung RI</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Menu (URL)</th>
                                            <th>Role</th>
                                            <th>Fungsi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Daftar Rawat Inap — /ri/daftar', 'hampir semua role klinis + admin', 'Pendaftaran, kamar, EMR, dokumen, administrasi — pusat kerja RI'],
                                            ['Gizi Rawat Inap — /ri/gizi', 'Gizi, Admin, Manager', 'Program diet harian + rekap porsi dapur + etiket diet'],
                                            ['PTO — /ri/pto', 'Apoteker', 'Pemantauan seluruh terapi obat pasien RI'],
                                            ['Sinkronisasi Tempat Tidur — /ri/update-tt-ri', 'Admin, Mr, Perawat, Dokter', 'Ketersediaan TT → Aplicares BPJS & SIRS'],
                                        ] as [$menu, $role, $fungsi])
                                            <tr>
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
                                Pasien BPJS butuh dua surat: <strong>SEP rawat inap</strong> (penjaminan) dan
                                <strong>SPRI</strong> (surat perintah rawat inap) — DPJP di SEP dijaga sinkron
                                dengan dokter kontrol di SPRI. Satu perawatan = satu baris
                                <span class="ds-code">rstxn_rihdrs</span> + JSON
                                <span class="ds-code">datadaftarri_json</span>.
                            </span>
                        </div>
                    </section>

                    {{-- ====== RI 2 — EMR HARIAN ====== --}}
                    <section x-show="section === 'ri-emr'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 2</div>
                        <h1 class="ds-display-md mb-4">EMR RI Harian</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            EMR RI adalah <strong>dokumen hidup</strong>: diisi banyak PPA (dokter, perawat,
                            apoteker, gizi, fisioterapis) setiap shift, selama berhari-hari. Dibuka dari tombol
                            <strong>Rekam Medis</strong> di Daftar RI.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => '≤24 jam', 'judul' => 'Pengkajian awal', 'sub' => 'perawat + dokter', 'chipWarna' => 'amber'],
                                ['chip' => 'tiap shift', 'judul' => 'CPPT & SBAR', 'sub' => 'semua PPA · review DPJP', 'chipWarna' => 'sky'],
                                ['chip' => 'harian', 'judul' => 'Visite & konsul', 'sub' => 'DPJP · dokter konsul', 'chipWarna' => 'sky'],
                                ['chip' => 'harian', 'judul' => 'Askep & penilaian', 'sub' => 'SDKI/SLKI/SIKI · risiko', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Order & dokumen', 'sub' => 'lab · rad · consent · edukasi'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Isi EMR RI</div>
                        <div class="ds-card-outline mb-6" style="padding:0;overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead>
                                        <tr>
                                            <th>Bagian</th>
                                            <th>Sifat</th>
                                            <th>Isi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ([
                                            ['Pengkajian Awal', 'sekali, ≤24 jam', 'Perawat + dokter: anamnesis, fisik, leveling DPJP (Utama/Rawat Gabung)'],
                                            ['CPPT', 'multi-entri harian', 'Catatan terintegrasi semua PPA (tab per profesi), di-review & TTD DPJP'],
                                            ['SBAR', 'multi-entri', 'Komunikasi antar shift/PPA'],
                                            ['Asuhan Keperawatan', 'harian', 'Diagnosa SDKI + luaran SLKI + intervensi SIKI'],
                                            ['Penilaian', 'berulang', 'Nyeri, risiko jatuh, dekubitus (Braden), gizi + program diet, C-SSRS'],
                                            ['Pemeriksaan & Order', 'sesuai kebutuhan', 'TTV + order Lab/Radiologi (cabang sama dgn RJ)'],
                                            ['Observasi & Obat', 'harian', 'Monitoring + administrasi pemberian obat'],
                                            ['Modul Dokumen', 'sesuai kebutuhan', 'Consent, edukasi terintegrasi, transfer, surveilans HAIs, akhir hayat, dll.'],
                                            ['E-Resep', 'per lembar', 'Resep harian — lihat tahap Apotek RI'],
                                        ] as [$bagian, $sifat, $isi])
                                            <tr>
                                                <td class="ds-td-token">{{ $bagian }}</td>
                                                <td class="ds-td-meta">{{ $sifat }}</td>
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
                                Karena banyak tangan menulis bersamaan, aturan mainnya ketat: entri CPPT hanya
                                bisa di-edit pemiliknya (hapus oleh supervisor), review/TTD oleh DPJP Utama,
                                dan semua aksi penting tercatat di <strong>Log Aktivitas</strong> kunjungan.
                            </span>
                        </div>
                    </section>

                    {{-- ====== RI 3 — APOTEK ====== --}}
                    <section x-show="section === 'ri-apotek'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 3</div>
                        <h1 class="ds-display-md mb-4">Apotek RI (per lembar resep)</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Beda kunci dari RJ/UGD: e-resep RI ditulis <strong>per lembar</strong> — satu
                            perawatan bisa punya banyak lembar resep (resep hari ke-1, ke-2, resep pulang…).
                            Setiap lembar berjalan sendiri di <strong>Antrian Apotek RI</strong>
                            (<span class="ds-code">/transaksi/ri-resep/antrian-ri-resep</span>).
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'Dokter', 'judul' => 'Tulis lembar resep', 'sub' => 'di EMR — bisa tiap hari', 'chipWarna' => 'sky'],
                                ['chip' => 'per lembar', 'judul' => 'Antrian Apotek RI', 'sub' => 'telaah resep & obat'],
                                ['chip' => null, 'judul' => 'Administrasi obat', 'sub' => 'harga menempel ke lembar'],
                                ['chip' => 'opsional', 'judul' => 'Kasir apotek', 'sub' => 'bayar duluan — kwitansi RESEP LUNAS', 'chipWarna' => 'amber'],
                                ['chip' => null, 'judul' => 'Obat ke ruangan', 'sub' => 'diberikan perawat', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Yang khas di farmasi RI</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-md space-y-3" style="list-style:disc; padding-left:1.2em">
                                <li><strong>Stempel antrean BPJS per lembar</strong> — taskId 6/7 hidup di
                                    tiap lembar resep, bukan di kunjungan.</li>
                                <li><strong>Kasir apotek RI</strong> — lembar resep bisa dilunasi duluan
                                    (kwitansi "RESEP LUNAS") tanpa menunggu pasien pulang; sisanya ikut
                                    tagihan akhir.</li>
                                <li><strong>PTO</strong> (<span class="ds-code">/ri/pto</span>) — apoteker
                                    memantau seluruh terapi obat pasien lintas lembar tanpa mengganggu
                                    e-resep dokter.</li>
                                <li><strong>Bon resep &amp; obat pinjam</strong> punya pos biayanya sendiri;
                                    retur obat menjadi pengurang (Rtn Obat).</li>
                            </ul>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Pemberian obat di ruangan dicatat perawat di EMR (administrasi obat) —
                                terpisah dari pelayanan lembar resep oleh apotek.
                            </span>
                        </div>
                    </section>

                    {{-- ====== RI 4 — ADMINISTRASI & PULANG ====== --}}
                    <section x-show="section === 'ri-administrasi'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 4</div>
                        <h1 class="ds-display-md mb-4">Administrasi &amp; Pasien Pulang</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Selama dirawat, biaya menumpuk otomatis di layar <strong>Administrasi RI</strong>
                            (dibuka dari Daftar RI). Saat dokter menyatakan boleh pulang, perawatan ditutup —
                            dengan beberapa penjaga agar tidak ada biaya yang tertinggal.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'harian', 'judul' => 'Biaya menumpuk', 'sub' => 'kamar · visite · konsul · obat'],
                                ['chip' => null, 'judul' => 'Pindah kamar?', 'sub' => 'riwayat kamar → tarif per hari'],
                                ['chip' => 'Dokter', 'judul' => 'Boleh pulang', 'sub' => 'resume / ringkasan pulang', 'chipWarna' => 'sky'],
                                ['chip' => 'guard', 'judul' => 'Cek gantungan', 'sub' => 'lab pending · OK belum transfer', 'chipWarna' => 'amber'],
                                ['chip' => 'ri_status P', 'judul' => 'Perawatan ditutup', 'sub' => 'EMR terkunci', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Pos biaya Administrasi RI</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="flex flex-wrap gap-2">
                                @foreach (['Kamar (room + service + perawatan)', 'Visit', 'Konsul', 'Jasa Medis', 'Jasa Dokter', 'Laborat', 'Radiologi', 'Operasi (OK)', 'Bon Resep', 'Obat Pinjam', 'Rtn Obat (pengurang)', 'Trf UGD/RJ', 'Admin RI', 'Admin Usia 14+', 'Lain-lain'] as $pos)
                                    <span class="px-2.5 py-1 text-sm rounded-full"
                                        style="background:var(--surface-card); color:var(--body)">{{ $pos }}</span>
                                @endforeach
                            </div>
                            <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">
                                Semua terisi otomatis dari modulnya masing-masing; "Trf UGD/RJ" membawa biaya
                                kunjungan asal saat pasien ditransfer. Tarif kamar & visite/konsul bisa
                                dikoreksi inline oleh role berwenang — setiap koreksi tercatat di audit log.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 mb-6 lg:grid-cols-2">
                            <div class="ds-card-outline" style="padding:0;overflow:hidden">
                                <div class="overflow-x-auto">
                                    <table class="ds-table">
                                        <thead>
                                            <tr>
                                                <th colspan="2">ri_status (perawatan)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ([['I', 'Dirawat (inap)'], ['P', 'Pulang'], ['F', 'Batal']] as [$kode, $arti])
                                                <tr>
                                                    <td class="ds-td-token">{{ $kode }}</td>
                                                    <td>{{ $arti }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="ds-card-outline" style="padding:16px 20px">
                                <span class="ds-spike" style="vertical-align:middle"></span>
                                <span class="ds-body-sm" style="color:var(--body-strong)">
                                    Guard pulang: pemulangan <strong>ditahan sistem</strong> bila masih ada
                                    pemeriksaan <strong>lab berstatus pending</strong> atau transaksi
                                    <strong>Kamar Operasi yang biayanya belum ditransfer</strong> — mencegah
                                    tagihan bocor karena transfer biaya mensyaratkan pasien masih dirawat.
                                </span>
                            </div>
                        </div>
                    </section>

                    {{-- ====== RI 5 — KASIR ====== --}}
                    <section x-show="section === 'ri-kasir'" x-cloak>
                        <div class="ds-eyebrow mb-3">Rawat Inap — Tahap 5</div>
                        <h1 class="ds-display-md mb-4">Kasir RI</h1>
                        <p class="ds-body-md mb-6" style="max-width:62ch">
                            Petugas Tu memakai <strong>Antrian Kasir RI</strong> dan <strong>Daftar Pasien
                            RI</strong> (<span class="ds-code">/transaksi/kasir/*</span>) untuk menutup
                            tagihan perawatan — termasuk menerima <strong>titipan/deposit</strong> selama
                            pasien masih dirawat.
                        </p>

                        <div class="ds-card-outline mb-6" style="padding:20px">
                            @include('pages.panduan-dev.alur-pelayanan.partial-pipeline', ['steps' => [
                                ['chip' => 'selama dirawat', 'judul' => 'Terima titipan', 'sub' => 'deposit mengurangi sisa', 'chipWarna' => 'amber'],
                                ['chip' => null, 'judul' => 'Pasien pulang', 'sub' => 'tagihan final terbentuk'],
                                ['chip' => 'kasir', 'judul' => 'Pembayaran', 'sub' => 'tunai/transfer · kwitansi', 'chipWarna' => 'sky'],
                                ['chip' => null, 'judul' => 'Lunas / piutang', 'sub' => 'sisa masuk monitoring piutang', 'chipWarna' => 'green'],
                            ]])
                        </div>

                        <div class="ds-caption-up mb-3" style="color:var(--muted)">Catatan kasir RI</div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <ul class="ds-body-md space-y-3" style="list-style:disc; padding-left:1.2em">
                                <li>Pasien BPJS murni: tagihan ditanggung penjamin — kasir menutup dengan
                                    selisih 0 (naik kelas / iur biaya dibayar pasien).</li>
                                <li>Lembar resep yang sudah dilunasi di kasir apotek (RESEP LUNAS) tidak
                                    tertagih dua kali.</li>
                                <li>Sisa tagihan yang tidak terbayar saat pulang masuk
                                    <strong>monitoring piutang pasien</strong> — ditagih/diangsur belakangan
                                    lewat modul Pembayaran Piutang.</li>
                            </ul>
                        </div>

                        <div class="ds-card-outline" style="padding:16px 20px">
                            <span class="ds-spike" style="vertical-align:middle"></span>
                            <span class="ds-body-sm" style="color:var(--body-strong)">
                                Setelah lunas, pembayaran membentuk jurnal kas di modul keuangan — sama
                                seperti RJ/UGD, hanya nilainya akumulasi seluruh perawatan.
                            </span>
                        </div>
                    </section>

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
