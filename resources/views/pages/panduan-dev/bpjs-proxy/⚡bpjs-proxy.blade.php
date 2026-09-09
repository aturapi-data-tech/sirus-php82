<?php

use Livewire\Component;
use App\Support\Bpjs\BpjsHttp;

// Tutorial WHITELIST IP BPJS — kenapa semua panggilan API BPJS harus keluar lewat
// VPS ber-IP terdaftar, cara memasang forward proxy (Squid) di VPS dari nol,
// cara aplikasi memakainya (BpjsHttp + .env), dan cara memeriksanya. Ditulis dari
// setup nyata 09/09/2026 (VPS Rocky 8, IP 38.103.170.232). Gaya sidebar sama dgn
// rujukan-kompetensi. Sumber ringkas: docs/bpjs-whitelist-ip-proxy.md.
//
// Halaman ini MEMERIKSA DIRINYA SENDIRI: kartu "Cek Sekarang" memanggil jalur
// BpjsHttp persis seperti trait BPJS, lalu melaporkan IP yang terlihat.
new class extends Component {
    /** Hasil pemeriksaan langsung: null = belum dijalankan. */
    public ?array $hasilCek = null;

    public function cekProxy(): void
    {
        $hasil = [
            'proxy'       => BpjsHttp::proxyUrlTersamar(),
            'whitelist'   => BpjsHttp::ipWhitelist(),
            'ipTerlihat'  => '',
            'cocok'       => null,
            'bpjsProd'    => '',
            'galat'       => '',
            'waktu'       => now()->format('d/m/Y H:i:s'),
        ];

        try {
            $mulai = microtime(true);
            $respons = BpjsHttp::mulai()->get((string) config('bpjs.ip_echo_url'));
            $hasil['ipTerlihat'] = trim((string) ($respons->json('ip') ?? $respons->body()));
            $hasil['lama'] = round(microtime(true) - $mulai, 2);
            $hasil['cocok'] = $hasil['whitelist'] !== '' ? $hasil['ipTerlihat'] === $hasil['whitelist'] : null;
        } catch (\Throwable $e) {
            $hasil['galat'] = $e->getMessage();
        }

        // Jangkauan ke host produksi BPJS: kode apa pun (biasanya 400 tanpa tanda tangan)
        // berarti tembus; yang dicari hanya "sampai atau tidak", tanpa kredensial.
        try {
            $mulai = microtime(true);
            $respons = BpjsHttp::mulai()->get('https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/poli/INT');
            $hasil['bpjsProd'] = 'HTTP ' . $respons->status() . ' dalam ' . round(microtime(true) - $mulai, 2) . ' dtk — terjangkau';
        } catch (\Throwable $e) {
            $hasil['bpjsProd'] = 'tidak terjangkau: ' . $e->getMessage();
        }

        $this->hasilCek = $hasil;
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap"
        rel="stylesheet" />

    @php
        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan & Kebijakan',
                'arsitektur' => 'Arsitektur: Proxy, Bukan VPN',
                'prasyarat' => 'Prasyarat',
            ],
            'Sisi VPS' => [
                'vps-masuk' => '1. Masuk & Amankan VPS',
                'vps-pasang' => '2. Pasang Squid',
                'vps-user' => '3. User Proxy & Berkas Sandi',
                'vps-konfig' => '4. Tulis squid.conf',
                'vps-nyalakan' => '5. Nyalakan & Port',
                'vps-uji' => '6. Uji dari Luar',
            ],
            'Sisi Aplikasi' => [
                'app-env' => 'Env & Config',
                'app-kode' => 'Kode: BpjsHttp & Trait',
                'app-cek' => 'Cek Sekarang (langsung)',
            ],
            'Operasional' => [
                'ops-produksi' => 'Ke Produksi',
                'ops-kunci-ip' => 'Mengunci ke IP RS',
                'ops-pantau' => 'Memantau & Rotasi Sandi',
                'faq' => 'Masalah → Penanganan',
            ],
            'Referensi' => [
                'referensi' => 'Dokumen & Sumber',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));
        $kodeGelap = 'margin:0; padding:20px; background:var(--surface-dark); color:var(--on-dark-soft); overflow-x:auto';
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
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Whitelist IP BPJS</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('panduan-dev') }}" wire:navigate
                        class="ds-btn ds-btn-secondary" style="height:34px; padding:6px 12px; font-size:13px">←
                        Panduan Dev</a>
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
                            Ringkasan: <span class="ds-code">docs/bpjs-whitelist-ip-proxy.md</span><br>
                            Kode: <span class="ds-code">App\Support\Bpjs\BpjsHttp</span><br>
                            Setup nyata 09/09/2026, VPS Rocky 8.
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    {{-- ====== PENDAHULUAN ====== --}}
                    <section x-show="section === 'pendahuluan'" x-cloak>
                        <div class="ds-eyebrow mb-3">01 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Whitelist IP BPJS &amp; Proxy VPS</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Sejak September 2026 BPJS Kesehatan hanya melayani panggilan API dari
                            <strong>IP publik yang didaftarkan faskes</strong> lewat "Formulir Pengajuan Akses
                            Bridging SIM". Yang didaftarkan RSI Madinah adalah IP sebuah <strong>VPS</strong>, bukan
                            IP kantor RS — karena IP kantor bisa berubah, dan tim pengembang bekerja dari banyak tempat.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Prinsip nomor satu</div>
                            <p class="ds-body-md" style="max-width:62ch">
                                <strong>Semua panggilan ke BPJS harus KELUAR lewat VPS itu</strong>, dari mana pun
                                aplikasi berjalan. Bukan aplikasinya yang pindah ke VPS — cukup paketnya yang
                                "dititipkan". Layanan lain (SATUSEHAT, Oracle, browser pengguna) tidak lewat VPS.
                            </p>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Data yang diajukan (09/09/2026)</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <tbody>
                                        <tr><td class="ds-td-strong">Faskes / PPK</td><td class="ds-body-sm">RSI Madinah / 0184R006</td></tr>
                                        <tr><td class="ds-td-strong">IP publik utama</td><td class="ds-body-sm"><span class="ds-code">38.103.170.232</span> (VPS Rocky 8, 2 core, 2 GB)</td></tr>
                                        <tr><td class="ds-td-strong">IP backup</td><td class="ds-body-sm">— (belum ada; bila kelak ada, tinggal VPS kedua + ubah .env)</td></tr>
                                        <tr><td class="ds-td-strong">Vendor / aplikasi</td><td class="ds-body-sm">Atur Rapi Data Technology / siRUS</td></tr>
                                        <tr><td class="ds-td-strong">Layanan terdampak</td><td class="ds-body-sm">VClaim, Antrean, Aplicares, iCare, SISRUTE, Apotek Online — semua yang memanggil <span class="ds-code">*.bpjs-kesehatan.go.id</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ds-callout ds-callout-info mb-6">
                            <p class="ds-body-sm"><strong>Sampai IP disetujui BPJS</strong>, panggilan lewat proxy tetap
                            berjalan normal — BPJS belum menolak IP mana pun. Setup ini disiapkan lebih dulu supaya saat
                            kebijakan mulai ditegakkan, cukup memastikan <span class="ds-code">.env</span> produksi terisi.</p>
                        </div>
                    </section>

                    {{-- ====== ARSITEKTUR ====== --}}
                    <section x-show="section === 'arsitektur'" x-cloak>
                        <div class="ds-eyebrow mb-3">02 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Arsitektur: Proxy, Bukan VPN</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Diagram</div>
                            <div class="overflow-x-auto">
                                <svg viewBox="0 0 860 220" style="min-width:700px; width:100%; font-family:inherit">
                                    <defs>
                                        <marker id="panah-proxy" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7"
                                            markerHeight="7" orient="auto-start-reverse">
                                            <path d="M 0 0 L 10 5 L 0 10 z" fill="var(--muted)" />
                                        </marker>
                                    </defs>
                                    <rect x="20" y="70" width="190" height="80" rx="12" fill="var(--surface-card)" />
                                    <text x="115" y="100" text-anchor="middle" font-size="14" font-weight="600" fill="var(--ink)">siRUS</text>
                                    <text x="115" y="120" text-anchor="middle" font-size="11" fill="var(--muted)">server RS / laptop dev</text>
                                    <text x="115" y="136" text-anchor="middle" font-size="11" fill="var(--muted)">IP bebas berubah</text>

                                    <line x1="210" y1="110" x2="330" y2="110" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-proxy)" />
                                    <text x="270" y="98" text-anchor="middle" font-size="11" fill="var(--muted)">CONNECT :3128</text>
                                    <text x="270" y="130" text-anchor="middle" font-size="11" fill="var(--muted)">user + sandi</text>

                                    <rect x="335" y="70" width="190" height="80" rx="12" fill="var(--surface-card)" />
                                    <text x="430" y="100" text-anchor="middle" font-size="14" font-weight="600" fill="var(--ink)">VPS — Squid</text>
                                    <text x="430" y="120" text-anchor="middle" font-size="11" fill="var(--muted)">38.103.170.232</text>
                                    <text x="430" y="136" text-anchor="middle" font-size="11" fill="var(--muted)">hanya ke *.bpjs-kesehatan.go.id</text>

                                    <line x1="525" y1="110" x2="645" y2="110" stroke="var(--muted)" stroke-width="2" marker-end="url(#panah-proxy)" />
                                    <text x="585" y="98" text-anchor="middle" font-size="11" fill="var(--muted)">HTTPS asli</text>
                                    <text x="585" y="130" text-anchor="middle" font-size="11" fill="var(--muted)">dari IP VPS</text>

                                    <rect x="650" y="70" width="190" height="80" rx="12" fill="var(--surface-card)" />
                                    <text x="745" y="100" text-anchor="middle" font-size="14" font-weight="600" fill="var(--ink)">API BPJS</text>
                                    <text x="745" y="120" text-anchor="middle" font-size="11" fill="var(--muted)">apijkn.bpjs-kesehatan.go.id</text>
                                    <text x="745" y="136" text-anchor="middle" font-size="11" fill="var(--muted)">melihat IP ter-whitelist</text>

                                    <text x="115" y="190" text-anchor="middle" font-size="11" fill="var(--muted)">SATUSEHAT, Oracle, browser → langsung, tidak lewat VPS</text>
                                </svg>
                            </div>
                        </div>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            <strong>Forward proxy</strong> berarti aplikasi berkata kepada VPS: "sambungkan saya ke
                            apijkn.bpjs-kesehatan.go.id". Sambungan HTTPS-nya tetap dari aplikasi ke BPJS (proxy tidak
                            membuka isinya), hanya alamat pengirim yang menjadi IP VPS. Tidak perlu VPN, tidak perlu
                            memindah server, tidak ada URL BPJS yang berubah.
                        </p>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Alternatif yang ditolak</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Cara</th><th>Kenapa tidak</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Pindahkan siRUS ke VPS</td><td class="ds-body-sm">Oracle & jaringan RS on-prem; latensi ke pengguna</td></tr>
                                        <tr><td class="ds-td-strong">VPN site-to-site</td><td class="ds-body-sm">Butuh perangkat/route di RS; tim dev di luar RS tetap tidak tercakup</td></tr>
                                        <tr><td class="ds-td-strong">Reverse proxy per URL BPJS</td><td class="ds-body-sm">Header Host & tanda tangan BPJS ikut berubah; harus mengedit semua URL</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    {{-- ====== PRASYARAT ====== --}}
                    <section x-show="section === 'prasyarat'" x-cloak>
                        <div class="ds-eyebrow mb-3">03 — Mulai</div>
                        <h1 class="ds-display-md mb-4">Prasyarat</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Butuh</th><th>Keterangan</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">VPS dengan IP publik statis</td><td class="ds-body-sm">Rocky 8 / AlmaLinux / CentOS Stream; 1 core 1 GB sudah cukup untuk Squid. IP inilah yang diajukan ke BPJS.</td></tr>
                                        <tr><td class="ds-td-strong">Akses root lewat SSH</td><td class="ds-body-sm">Dari terminal komputer Anda (bukan dari sesi asisten — SSH butuh sesi interaktif).</td></tr>
                                        <tr><td class="ds-td-strong">Formulir BPJS terkirim</td><td class="ds-body-sm">Nama faskes, kode PPK, IP publik utama, data vendor; ditandatangani pemohon &amp; vendor.</td></tr>
                                        <tr><td class="ds-td-strong">Akses .env server siRUS</td><td class="ds-body-sm">Untuk mengisi <span class="ds-code">BPJS_PROXY_URL</span> &amp; <span class="ds-code">BPJS_IP_WHITELIST</span>.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ds-callout ds-callout-warning mb-6">
                            <p class="ds-body-sm"><strong>Jangan pernah menempel sandi root VPS di percakapan/chat.</strong>
                            Kalau sudah terlanjur, langkah pertama di bawah adalah menggantinya. Sandi hanya hidup di
                            VPS dan di <span class="ds-code">.env</span> server.</p>
                        </div>
                    </section>

                    {{-- ====== 1. MASUK & AMANKAN ====== --}}
                    <section x-show="section === 'vps-masuk'" x-cloak>
                        <div class="ds-eyebrow mb-3">04 — Sisi VPS</div>
                        <h1 class="ds-display-md mb-4">1. Masuk &amp; Amankan VPS</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">Dari terminal komputer Anda:</p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}">ssh root@38.103.170.232          <span style="color:#8b948c"># pertama kali: jawab "yes" pada fingerprint</span>
passwd                            <span style="color:#8b948c"># ganti sandi root SEBELUM apa pun</span></pre>
                        </div>
                        <p class="ds-body-md mb-4" style="max-width:62ch">Kenali keadaan awal server:</p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}">cat /etc/os-release | head -2; dnf list installed squid 2>/dev/null | tail -1; ss -ltn</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Hasil nyata 09/09/2026</div>
                            <p class="ds-body-sm">Rocky Linux 8.10, Squid belum terpasang, port terbuka hanya <span class="ds-code">22</span> (SSH)
                            dan <span class="ds-code">111</span> (rpcbind — bawaan, tidak dipakai, ditutup di langkah 5).</p>
                        </div>
                        <div class="ds-callout ds-callout-info">
                            <p class="ds-body-sm">Lanjutan yang disarankan setelah semua selesai: matikan login sandi SSH
                            (<span class="ds-code">PasswordAuthentication no</span> di <span class="ds-code">/etc/ssh/sshd_config</span>)
                            dan pakai kunci SSH.</p>
                        </div>
                    </section>

                    {{-- ====== 2. PASANG SQUID ====== --}}
                    <section x-show="section === 'vps-pasang'" x-cloak>
                        <div class="ds-eyebrow mb-3">05 — Sisi VPS</div>
                        <h1 class="ds-display-md mb-4">2. Pasang Squid</h1>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}">dnf install -y squid httpd-tools   <span style="color:#8b948c"># httpd-tools = htpasswd, pembuat sandi proxy</span>
squid -v | head -1                  <span style="color:#8b948c"># Squid Cache: Version 4.15</span></pre>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted); max-width:62ch">
                            Squid adalah forward proxy standar Linux. Kita memakainya polos: tanpa cache, tanpa SSL bump,
                            hanya meneruskan sambungan <span class="ds-code">CONNECT</span> ke host yang diizinkan.
                        </p>
                    </section>

                    {{-- ====== 3. USER PROXY ====== --}}
                    <section x-show="section === 'vps-user'" x-cloak>
                        <div class="ds-eyebrow mb-3">06 — Sisi VPS</div>
                        <h1 class="ds-display-md mb-4">3. User Proxy &amp; Berkas Sandi</h1>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}">htpasswd -c /etc/squid/passwd sirus          <span style="color:#8b948c"># diminta sandi 2x; BEDA dari sandi root</span>
chgrp squid /etc/squid/passwd && chmod 640 /etc/squid/passwd
ls -l /usr/lib64/squid/basic_ncsa_auth       <span style="color:#8b948c"># program autentikasi yang dirujuk squid.conf</span></pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Kenapa chmod 640</div>
                            <p class="ds-body-sm" style="max-width:62ch">Bawaan <span class="ds-code">htpasswd</span> membuat berkas
                            yang bisa dibaca semua user di VPS. Dengan grup <span class="ds-code">squid</span> + mode 640,
                            hanya root dan proses Squid yang bisa membacanya. Isinya hash, bukan sandi asli, tapi tetap
                            tidak perlu terbuka.</p>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted)">Menambah user lain nanti: <span class="ds-code">htpasswd /etc/squid/passwd namauser</span>
                        (tanpa <span class="ds-code">-c</span>, karena <span class="ds-code">-c</span> membuat ulang berkas dan menghapus user lama).</p>
                    </section>

                    {{-- ====== 4. SQUID.CONF ====== --}}
                    <section x-show="section === 'vps-konfig'" x-cloak>
                        <div class="ds-eyebrow mb-3">07 — Sisi VPS</div>
                        <h1 class="ds-display-md mb-4">4. Tulis squid.conf</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Simpan konfigurasi bawaan (74 baris, isinya <span class="ds-code">localnet</span> yang tidak
                            relevan), lalu ganti seluruh berkas dengan 21 baris ini. Tempel dari <span class="ds-code">cp</span>
                            sampai <span class="ds-code">EOF</span> sekaligus:
                        </p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}">cp /etc/squid/squid.conf /etc/squid/squid.conf.asli
cat > /etc/squid/squid.conf &lt;&lt;'EOF'
http_port 3128

<span style="color:#8b948c"># Lapis 1: user + sandi (/etc/squid/passwd)</span>
auth_param basic program /usr/lib64/squid/basic_ncsa_auth /etc/squid/passwd
auth_param basic realm proxy-bpjs
acl terautentikasi proxy_auth REQUIRED

<span style="color:#8b948c"># Lapis 2: tujuan yang boleh — hanya BPJS + penunjuk IP untuk uji</span>
acl bpjs dstdomain .bpjs-kesehatan.go.id
acl ipecho dstdomain api.ipify.org
acl port_aman port 443 80

http_access allow terautentikasi bpjs port_aman
http_access allow terautentikasi ipecho port_aman
http_access deny all

<span style="color:#8b948c"># Tanpa cache, tanpa jejak identitas peminta</span>
cache deny all
via off
forwarded_for delete
request_header_access X-Forwarded-For deny all
EOF
squid -k parse 2>&1 | grep -i "error\|fatal"; echo "parse selesai"   <span style="color:#8b948c"># sah bila hanya "parse selesai"</span></pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Tiga lapis pengaman</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Lapis</th><th>Baris</th><th>Artinya</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Sandi</td><td class="ds-body-sm"><span class="ds-code">proxy_auth REQUIRED</span></td><td class="ds-body-sm">Tanpa user/sandi, Squid menjawab 407. Inilah yang dipakai siRUS lewat <span class="ds-code">BPJS_PROXY_URL</span>.</td></tr>
                                        <tr><td class="ds-td-strong">Tujuan</td><td class="ds-body-sm"><span class="ds-code">dstdomain .bpjs-kesehatan.go.id</span></td><td class="ds-body-sm">Ke Google atau situs lain ditolak 403 walau sandi benar — proxy tak bisa jadi "internet gratis".</td></tr>
                                        <tr><td class="ds-td-strong">IP asal (opsional)</td><td class="ds-body-sm"><span class="ds-code">acl rs src IP_RS/32</span></td><td class="ds-body-sm">Tidak dipakai pada mode <em>bebas lokasi</em> ini; lihat "Mengunci ke IP RS" bila ingin dikunci saat produksi.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ds-callout ds-callout-warning">
                            <p class="ds-body-sm"><strong>Jebakan nyata:</strong> saat setup, blok <span class="ds-code">cat &gt; …</span> sempat
                            tidak tertempel sehingga berkas masih 74 baris bawaan dan <span class="ds-code">sed</span> tidak mengubah apa-apa.
                            Selalu cek <span class="ds-code">grep -c "" /etc/squid/squid.conf</span> (harus ±21) sebelum lanjut.</p>
                        </div>
                    </section>

                    {{-- ====== 5. NYALAKAN ====== --}}
                    <section x-show="section === 'vps-nyalakan'" x-cloak>
                        <div class="ds-eyebrow mb-3">08 — Sisi VPS</div>
                        <h1 class="ds-display-md mb-4">5. Nyalakan &amp; Port</h1>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}">systemctl enable --now squid
systemctl disable --now rpcbind rpcbind.socket      <span style="color:#8b948c"># tutup port 111 yang tidak dipakai</span>
systemctl is-active squid; ss -ltn | grep -E ":3128|:22|:111"</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:20px">
                            <div class="ds-caption-up mb-3" style="color:var(--muted)">Firewall</div>
                            <p class="ds-body-sm" style="max-width:62ch">VPS ini <strong>tidak memasang firewalld</strong>
                            (<span class="ds-code">firewall-cmd: command not found</span>) sehingga port 3128 langsung
                            terjangkau dari internet. Itu diterima karena Squid sendiri sudah mengunci lewat sandi + tujuan.
                            Bila firewalld ada:
                            <span class="ds-code">firewall-cmd --permanent --add-port=3128/tcp && firewall-cmd --reload</span>.</p>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted)">Hasil yang benar: <span class="ds-code">active</span>,
                        baris <span class="ds-code">*:3128</span> muncul di <span class="ds-code">ss</span>, dan tidak ada lagi baris <span class="ds-code">:111</span>.</p>
                    </section>

                    {{-- ====== 6. UJI ====== --}}
                    <section x-show="section === 'vps-uji'" x-cloak>
                        <div class="ds-eyebrow mb-3">09 — Sisi VPS</div>
                        <h1 class="ds-display-md mb-4">6. Uji dari Luar</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">Dari laptop atau server siRUS (ganti <span class="ds-code">SANDI</span>):</p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}"><span style="color:#8b948c"># 1) tanpa sandi → ditolak (407; untuk HTTPS curl melapor exit 56, itu 407 pada tahap CONNECT)</span>
curl -s -o /dev/null -w "%{http_code}\n" -x http://38.103.170.232:3128 http://api.ipify.org
<span style="color:#8b948c"># 2) dengan sandi → IP VPS</span>
curl -x http://sirus:SANDI@38.103.170.232:3128 https://api.ipify.org; echo
<span style="color:#8b948c"># 3) tujuan di luar BPJS → 403 walau sandi benar</span>
curl -s -o /dev/null -w "%{http_code}\n" -x http://sirus:SANDI@38.103.170.232:3128 http://neverssl.com
<span style="color:#8b948c"># 4) host produksi BPJS tembus (400 = sampai, hanya kurang tanda tangan)</span>
curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" -x http://sirus:SANDI@38.103.170.232:3128 https://apijkn.bpjs-kesehatan.go.id/vclaim-rest/referensi/poli/INT</pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">Hasil nyata 09/09/2026</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Uji</th><th>Hasil</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">Tanpa sandi</td><td class="ds-body-sm">407 Proxy Authentication Required</td></tr>
                                        <tr><td class="ds-td-strong">ipify dengan sandi</td><td class="ds-body-sm"><span class="ds-code">38.103.170.232</span></td></tr>
                                        <tr><td class="ds-td-strong">Google / neverssl dengan sandi</td><td class="ds-body-sm">403 (ditolak)</td></tr>
                                        <tr><td class="ds-td-strong">apijkn (produksi) lewat proxy</td><td class="ds-body-sm">HTTP 400 dalam 0,15 dtk — tembus</td></tr>
                                        <tr><td class="ds-td-strong">apijkn-dev lewat proxy &amp; langsung</td><td class="ds-body-sm">habis waktu dua-duanya — host dev BPJS sedang mati, bukan urusan proxy</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ds-callout ds-callout-warning">
                            <p class="ds-body-sm"><strong>Jebakan nyata:</strong> uji dengan variabel shell yang ternyata kosong
                            (<span class="ds-code">-x ""</span>) membuat curl berjalan <em>tanpa</em> proxy dan Google "lolos 200" —
                            terlihat seperti proxy bocor padahal proxy tidak dipakai. Saat hasil aneh, ulangi dengan alamat proxy
                            ditulis eksplisit.</p>
                        </div>
                    </section>

                    {{-- ====== APP: ENV ====== --}}
                    <section x-show="section === 'app-env'" x-cloak>
                        <div class="ds-eyebrow mb-3">10 — Sisi Aplikasi</div>
                        <h1 class="ds-display-md mb-4">Env &amp; Config</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">Dua baris di <span class="ds-code">.env</span>, lalu <span class="ds-code">php artisan config:clear</span>:</p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}">BPJS_PROXY_AKTIF=true            <span style="color:#8b948c"># SAKLAR — bawaan false = langsung (produksi saat ini)</span>
BPJS_PROXY_URL=http://sirus:SANDI@38.103.170.232:3128
BPJS_IP_WHITELIST=38.103.170.232
<span style="color:#8b948c"># BPJS_HTTP_TIMEOUT=8            # batas waktu panggilan (dtk)</span>
<span style="color:#8b948c"># BPJS_HTTP_CONNECT_TIMEOUT=3    # batas waktu sambung (dtk)</span></pre>
                        </div>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="ds-caption-up" style="color:var(--muted); padding:14px 24px 6px">config/bpjs.php</div>
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Kunci</th><th>Env</th><th>Arti</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">proxy_aktif</td><td class="ds-body-sm">BPJS_PROXY_AKTIF</td><td class="ds-body-sm"><strong>Saklar.</strong> false (bawaan) = langsung, persis sebelum kebijakan — keadaan produksi saat ini. true = lewat proxy_url. Dibuat terpisah supaya URL bisa tersimpan duluan tanpa dipakai.</td></tr>
                                        <tr><td class="ds-td-strong">proxy_url</td><td class="ds-body-sm">BPJS_PROXY_URL</td><td class="ds-body-sm">Alamat proxy; hanya dipakai bila saklar hidup</td></tr>
                                        <tr><td class="ds-td-strong">ip_whitelist</td><td class="ds-body-sm">BPJS_IP_WHITELIST</td><td class="ds-body-sm">Pembanding untuk <span class="ds-code">bpjs:cek-proxy</span>; tidak memengaruhi panggilan</td></tr>
                                        <tr><td class="ds-td-strong">timeout / connect_timeout</td><td class="ds-body-sm">BPJS_HTTP_TIMEOUT / _CONNECT_TIMEOUT</td><td class="ds-body-sm">8 / 3 dtk. Panggilan sinkron tanpa batas = layar membeku saat BPJS gangguan</td></tr>
                                        <tr><td class="ds-td-strong">ip_echo_url</td><td class="ds-body-sm">BPJS_IP_ECHO_URL</td><td class="ds-body-sm">Penunjuk IP untuk pemeriksaan (bawaan api.ipify.org)</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ds-callout ds-callout-info">
                            <p class="ds-body-sm"><strong>Jebakan nyata:</strong> baris <span class="ds-code">.env</span> berawalan spasi tetap
                            dibaca Laravel, tetapi luput dari <span class="ds-code">grep "^BPJS_PROXY_URL="</span> di skrip shell. Tulis tanpa spasi di depan.</p>
                        </div>
                    </section>

                    {{-- ====== APP: KODE ====== --}}
                    <section x-show="section === 'app-kode'" x-cloak>
                        <div class="ds-eyebrow mb-3">11 — Sisi Aplikasi</div>
                        <h1 class="ds-display-md mb-4">Kode: BpjsHttp &amp; Trait</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Satu pintu keluar: <span class="ds-code">App\Support\Bpjs\BpjsHttp::mulai()</span> membuat
                            <span class="ds-code">PendingRequest</span> dengan batas waktu dan — bila env terisi — opsi
                            <span class="ds-code">proxy</span> Guzzle. Semua trait BPJS memakainya:
                        </p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}"><span style="color:#8b948c">// dulu (51 titik di VclaimTrait 23, AntrianTrait 17, AplicaresTrait 5, SisruteTrait 5, iCareTrait 1)</span>
$response = Http::timeout(8)->connectTimeout(3)->withHeaders($signature)->get($url);

<span style="color:#8b948c">// kini</span>
$response = BpjsHttp::mulai()->withHeaders($signature)->get($url);</pre>
                        </div>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}"><span style="color:#8b948c">// inti BpjsHttp::mulai() — lewatProxy() = saklar hidup DAN URL terisi</span>
$permintaan = Http::timeout(self::timeout())->connectTimeout(self::connectTimeout());
if (self::lewatProxy()) {
    $permintaan = $permintaan->withOptions(['proxy' => self::proxyUrl()]);   <span style="color:#8b948c">// http & https (CONNECT)</span>
}
return $permintaan;</pre>
                        </div>
                        <div class="ds-callout ds-callout-warning mb-6">
                            <p class="ds-body-sm"><strong>Aturan:</strong> panggilan BPJS baru <strong>wajib</strong> lewat
                            <span class="ds-code">BpjsHttp::mulai()</span>, jangan <span class="ds-code">Http::</span> langsung.
                            Kalau tidak, panggilan itu keluar dari IP RS dan ditolak BPJS tanpa pesan yang jelas.
                            SATUSEHAT, Mindray, dan layanan lain <em>bukan</em> pemakai kelas ini.</p>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted)">Kenapa bukan <span class="ds-code">Http::globalOptions</span>: opsi global
                        ikut mengalihkan SATUSEHAT dan semua HTTP keluar lewat VPS — pemborosan dan risiko.
                        Kenapa bukan env <span class="ds-code">HTTPS_PROXY</span>: sama, dan Guzzle membacanya diam-diam.</p>
                    </section>

                    {{-- ====== APP: CEK SEKARANG ====== --}}
                    <section x-show="section === 'app-cek'" x-cloak>
                        <div class="ds-eyebrow mb-3">12 — Sisi Aplikasi</div>
                        <h1 class="ds-display-md mb-4">Cek Sekarang (langsung)</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Kartu ini memanggil jalur <span class="ds-code">BpjsHttp</span> yang sama dengan trait BPJS —
                            padanan <span class="ds-code">php artisan bpjs:cek-proxy</span> — dari server tempat halaman ini
                            berjalan. Tidak mengirim kredensial BPJS apa pun.
                        </p>
                        <div class="ds-card-outline mb-4" style="padding:20px">
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" wire:click="cekProxy" wire:loading.attr="disabled" class="ds-btn ds-btn-primary">
                                    <span wire:loading.remove wire:target="cekProxy">Cek sekarang</span>
                                    <span wire:loading wire:target="cekProxy">Memeriksa… (maks. ±20 dtk)</span>
                                </button>
                                <span class="ds-body-sm" style="color:var(--muted)">Proxy terpasang: <span class="ds-code">{{ \App\Support\Bpjs\BpjsHttp::proxyUrlTersamar() }}</span></span>
                            </div>
                            @if ($hasilCek)
                                <div class="mt-4 overflow-x-auto">
                                    <table class="ds-table">
                                        <tbody>
                                            <tr><td class="ds-td-strong">Waktu</td><td class="ds-body-sm">{{ $hasilCek['waktu'] }}</td></tr>
                                            <tr><td class="ds-td-strong">Proxy</td><td class="ds-body-sm">{{ $hasilCek['proxy'] }}</td></tr>
                                            <tr><td class="ds-td-strong">IP whitelist</td><td class="ds-body-sm">{{ $hasilCek['whitelist'] ?: '(BPJS_IP_WHITELIST kosong)' }}</td></tr>
                                            <tr><td class="ds-td-strong">IP terlihat</td><td class="ds-body-sm">
                                                @if ($hasilCek['galat'] !== '')
                                                    <span style="color:var(--error)">gagal: {{ $hasilCek['galat'] }}</span>
                                                @else
                                                    <span class="ds-code">{{ $hasilCek['ipTerlihat'] }}</span> ({{ $hasilCek['lama'] ?? '-' }} dtk)
                                                    @if ($hasilCek['cocok'] === true) <strong style="color:var(--primary)">— COCOK</strong>
                                                    @elseif ($hasilCek['cocok'] === false) <strong style="color:var(--error)">— TIDAK COCOK</strong>
                                                    @endif
                                                @endif
                                            </td></tr>
                                            <tr><td class="ds-td-strong">Host produksi BPJS</td><td class="ds-body-sm">{{ $hasilCek['bpjsProd'] }}</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}"><span style="color:#8b948c"># padanan di terminal server siRUS</span>
php artisan bpjs:cek-proxy
<span style="color:#8b948c"># Proxy        : http://sirus:***@38.103.170.232:3128
# IP whitelist : 38.103.170.232
# IP terlihat  : 38.103.170.232 (0.42 dtk)
# COCOK — BPJS akan melihat IP yang di-whitelist.</span></pre>
                        </div>
                    </section>

                    {{-- ====== OPS: PRODUKSI ====== --}}
                    <section x-show="section === 'ops-produksi'" x-cloak>
                        <div class="ds-eyebrow mb-3">13 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Ke Produksi</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>#</th><th>Langkah</th><th>Bukti selesai</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-meta">1</td><td class="ds-body-sm">Kode yang memuat <span class="ds-code">BpjsHttp</span> sudah ada di server (branch/rilis yang memuatnya)</td><td class="ds-body-sm"><span class="ds-code">php artisan list | grep bpjs:cek-proxy</span> menampilkan perintahnya</td></tr>
                                        <tr><td class="ds-td-meta">2</td><td class="ds-body-sm">Isi <span class="ds-code">BPJS_PROXY_URL</span> &amp; <span class="ds-code">BPJS_IP_WHITELIST</span> di <span class="ds-code">.env</span> server (saklar boleh masih <span class="ds-code">false</span>)</td><td class="ds-body-sm"><span class="ds-code">bpjs:cek-proxy</span> menampilkan "NONAKTIF … URL tersimpan"</td></tr>
                                        <tr><td class="ds-td-meta">2b</td><td class="ds-body-sm">Saat BPJS menegakkan whitelist: <span class="ds-code">BPJS_PROXY_AKTIF=true</span> + <span class="ds-code">config:clear</span></td><td class="ds-body-sm"><span class="ds-code">bpjs:cek-proxy</span> → COCOK</td></tr>
                                        <tr><td class="ds-td-meta">3</td><td class="ds-body-sm">Coba satu transaksi nyata: cari peserta VClaim / ambil antrean</td><td class="ds-body-sm">Respons BPJS normal (bukan 408/timeout)</td></tr>
                                        <tr><td class="ds-td-meta">4</td><td class="ds-body-sm">Pantau log Squid beberapa hari pertama</td><td class="ds-body-sm">Baris <span class="ds-code">TCP_TUNNEL/200</span> ke host BPJS</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ds-callout ds-callout-info">
                            <p class="ds-body-sm">Bila VPS mati, panggilan BPJS gagal dalam ±3 dtk dengan pesan (bukan membeku), dan
                            layar lain tetap jalan. Penanganan darurat: <span class="ds-code">BPJS_PROXY_AKTIF=false</span> +
                            <span class="ds-code">config:clear</span> — kembali langsung (selama BPJS belum menegakkan whitelist untuk IP RS).</p>
                        </div>
                    </section>

                    {{-- ====== OPS: KUNCI IP ====== --}}
                    <section x-show="section === 'ops-kunci-ip'" x-cloak>
                        <div class="ds-eyebrow mb-3">14 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Mengunci ke IP RS</h1>
                        <p class="ds-body-md mb-4" style="max-width:62ch">
                            Mode sekarang <strong>bebas lokasi</strong> (cukup sandi). Saat produksi mapan, proxy bisa dikunci
                            agar hanya jaringan RS yang boleh mengetuk. Cari IP publik RS dari server siRUS:
                            <span class="ds-code">curl -s https://api.ipify.org</span>.
                        </p>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}"><span style="color:#8b948c"># di /etc/squid/squid.conf — tambah acl asal, lalu sisipkan ke dua baris allow</span>
acl rs src IP_RS/32
http_access allow rs terautentikasi bpjs port_aman
http_access allow rs terautentikasi ipecho port_aman
http_access deny all

systemctl reload squid</pre>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted)">Dev dari luar RS akan ditolak 403 walau sandi benar — itu memang tujuannya.
                        Tambahkan <span class="ds-code">acl</span> kedua untuk IP rumah/kantor dev bila perlu. Kalau IP RS berganti, ubah satu baris + reload; aplikasi tidak disentuh.</p>
                    </section>

                    {{-- ====== OPS: PANTAU ====== --}}
                    <section x-show="section === 'ops-pantau'" x-cloak>
                        <div class="ds-eyebrow mb-3">15 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Memantau &amp; Rotasi Sandi</h1>
                        <div class="ds-card-outline mb-4" style="padding:0; overflow:hidden">
<pre class="ds-code" style="{{ $kodeGelap }}"><span style="color:#8b948c"># lalu lintas hidup: siapa memanggil host apa, kode apa</span>
tail -f /var/log/squid/access.log
<span style="color:#8b948c"># TCP_TUNNEL/200  apijkn.bpjs-kesehatan.go.id:443  → normal
# TCP_DENIED/407  → tanpa/salah sandi
# TCP_DENIED/403  → tujuan di luar daftar</span>

<span style="color:#8b948c"># ganti sandi user proxy (lalu ubah BPJS_PROXY_URL di .env server + config:clear)</span>
htpasswd /etc/squid/passwd sirus

<span style="color:#8b948c"># status & muat ulang konfigurasi tanpa memutus sambungan</span>
systemctl status squid --no-pager | head -5
systemctl reload squid</pre>
                        </div>
                        <p class="ds-body-sm" style="color:var(--muted)">Pembaruan OS berkala: <span class="ds-code">dnf update -y</span>.
                        Squid tanpa cache, jadi tidak ada disk yang perlu dibersihkan.</p>
                    </section>

                    {{-- ====== FAQ ====== --}}
                    <section x-show="section === 'faq'" x-cloak>
                        <div class="ds-eyebrow mb-3">16 — Operasional</div>
                        <h1 class="ds-display-md mb-4">Masalah → Penanganan</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Gejala</th><th>Sebab</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">bpjs:cek-proxy → "Gagal menghubungi … timed out after 3001 ms"</td><td class="ds-body-sm">Squid mati / port 3128 tertutup / VPS down</td><td class="ds-body-sm"><span class="ds-code">systemctl status squid</span>; <span class="ds-code">ss -ltn | grep 3128</span>; cek panel VPS</td></tr>
                                        <tr><td class="ds-td-strong">cek-proxy → "TIDAK COCOK", IP terlihat = IP RS</td><td class="ds-body-sm">Proxy tidak terpakai: saklar <span class="ds-code">BPJS_PROXY_AKTIF</span> masih false / env kosong / belum <span class="ds-code">config:clear</span> / skema URL salah</td><td class="ds-body-sm">Periksa <span class="ds-code">.env</span>, jalankan <span class="ds-code">config:clear</span>, pastikan diawali <span class="ds-code">http://</span></td></tr>
                                        <tr><td class="ds-td-strong">HTTP 407 dari proxy</td><td class="ds-body-sm">User/sandi proxy salah (atau berubah)</td><td class="ds-body-sm">Samakan dengan <span class="ds-code">/etc/squid/passwd</span>; <span class="ds-code">htpasswd</span> ulang bila lupa</td></tr>
                                        <tr><td class="ds-td-strong">HTTP 403 dari proxy</td><td class="ds-body-sm">Tujuan di luar <span class="ds-code">dstdomain</span>, atau IP asal ditolak (mode terkunci)</td><td class="ds-body-sm">BPJS memakai host baru? tambah ke <span class="ds-code">acl bpjs</span>; atau tambah <span class="ds-code">acl src</span></td></tr>
                                        <tr><td class="ds-td-strong">apijkn-dev habis waktu, apijkn (prod) tembus</td><td class="ds-body-sm">Host dev BPJS sedang mati (terjadi 09/09/2026, langsung pun gagal)</td><td class="ds-body-sm">Bukan urusan proxy; tunggu / cek grup BPJS</td></tr>
                                        <tr><td class="ds-td-strong">BPJS menjawab "IP tidak terdaftar" walau cek-proxy COCOK</td><td class="ds-body-sm">Pengajuan whitelist belum diproses BPJS</td><td class="ds-body-sm">Tindak lanjuti formulir ke kantor cabang; lampirkan hasil <span class="ds-code">bpjs:cek-proxy</span></td></tr>
                                        <tr><td class="ds-td-strong">Google lewat proxy "lolos 200" saat uji curl</td><td class="ds-body-sm">Variabel proxy kosong → curl tanpa proxy</td><td class="ds-body-sm">Ulangi dengan alamat proxy eksplisit</td></tr>
                                        <tr><td class="ds-td-strong">Panggilan BPJS baru selalu gagal, yang lama normal</td><td class="ds-body-sm">Kode baru memakai <span class="ds-code">Http::</span> langsung</td><td class="ds-body-sm">Ganti ke <span class="ds-code">BpjsHttp::mulai()</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    {{-- ====== REFERENSI ====== --}}
                    <section x-show="section === 'referensi'" x-cloak>
                        <div class="ds-eyebrow mb-3">17 — Referensi</div>
                        <h1 class="ds-display-md mb-4">Dokumen &amp; Sumber</h1>
                        <div class="ds-card-outline mb-6" style="padding:0; overflow:hidden">
                            <div class="overflow-x-auto">
                                <table class="ds-table">
                                    <thead><tr><th>Sumber</th><th>Isi</th></tr></thead>
                                    <tbody>
                                        <tr><td class="ds-td-strong">docs/bpjs-whitelist-ip-proxy.md</td><td class="ds-body-sm">Ringkasan setup &amp; aturan pengembangan (versi teks, untuk repo)</td></tr>
                                        <tr><td class="ds-td-strong">config/bpjs.php</td><td class="ds-body-sm">Kunci konfigurasi &amp; env</td></tr>
                                        <tr><td class="ds-td-strong">app/Support/Bpjs/BpjsHttp.php</td><td class="ds-body-sm">Pintu keluar tunggal panggilan BPJS</td></tr>
                                        <tr><td class="ds-td-strong">app/Console/Commands/BpjsCekProxy.php</td><td class="ds-body-sm"><span class="ds-code">php artisan bpjs:cek-proxy</span></td></tr>
                                        <tr><td class="ds-td-strong">Formulir Pengajuan Akses Bridging SIM (BPJS)</td><td class="ds-body-sm">Identitas faskes, IP publik utama/backup, data vendor, pernyataan &amp; komitmen</td></tr>
                                        <tr><td class="ds-td-strong">Squid 4 — squid.conf reference</td><td class="ds-body-sm"><span class="ds-code">http_access</span>, <span class="ds-code">acl dstdomain</span>, <span class="ds-code">auth_param basic</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
