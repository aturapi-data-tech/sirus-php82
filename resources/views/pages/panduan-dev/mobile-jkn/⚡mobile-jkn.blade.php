<?php

use Livewire\Component;

// Panduan API Mobile JKN (Antrean Online BPJS) — cara kerja & operasional.
// Halaman referensi tanpa state form. Sumber isi: docs/mobile-jkn-api.md dan
// app/Http/Controllers/Api/MobileJkn/AntrolBPJSController.php.
new class extends Component {
    public function snippets(): array
    {
        return [

'arsitektur' => <<<'TXT'
  Aplikasi Mobile JKN (HP pasien)
            │
            ▼
  Server BPJS ──── memanggil URL RS yang terdaftar di BPJS ────┐
                                                              ▼
                          ┌──────────── SIRUS (satu aplikasi) ────────────┐
   host API  ───────────► │ routes/mobile-jkn.php  (grup api, tanpa login) │
   (internet)             │   /auth  /ambilantrean  /checkinantrean ...    │
                          │                                                │
   host SIRUS ──────────► │ routes/web.php  (halaman SIRUS, wajib login)   │
   (LAN RS)               └───────────────┬────────────────────────────────┘
                                          │ Oracle (DB yang sama)
                                          ▼
                 referensi_mobilejkn_bpjs · rstxn_rjhdrs · api_log_status
                                          │
                                          ▼  outbound (setelah checkin)
                          Antrean RS BPJS  antrean/add · antrean/updatewaktu
TXT,

'env' => <<<'TXT'
# .env — host API (tanpa http:// dan port). Kosong = API Mobile JKN tidak aktif.
MOBILE_JKN_HOST=apijkn.rsimadinah.com

# Kredensial Antrean RS BPJS (dipakai juga oleh modul Booking RJ SIRUS)
ANTRIAN_URL=<base URL Antrean RS dari BPJS, diakhiri "/">
ANTRIAN_CONS_ID=xxxxx
ANTRIAN_SECRET_KEY=xxxxx
ANTRIAN_USER_KEY=xxxxx

# Booking hari yang sama: true = ditolak (wajib H+1), false = diizinkan
ANTRIAN_DISALLOW_SAMEDAY=true
TXT,

'token' => <<<'TXT'
# 1) Minta token — username & password = user SIRUS khusus BPJS (tabel users)
GET https://apijkn.rsimadinah.com/auth
x-username: <user>
x-password: <password>

→ 200 { "response": { "token": "eyJ0eXAi..." }, "metadata": { "message": "Ok", "code": 200 } }
→ 201 { "metadata": { "message": "Unauthorized (Username dan Password Salah)", "code": 201 } }

# 2) Semua endpoint lain wajib membawa:
x-username: <user>
x-token:    <token dari langkah 1>
TXT,

'ambil' => <<<'TXT'
POST https://apijkn.rsimadinah.com/ambilantrean
{
  "nomorkartu": "0001234567890",      13 digit
  "nik": "3504xxxxxxxxxxxx",          16 digit, harus sama dengan rsmst_pasiens.nik_bpjs
  "nohp": "08xxxxxxxxxx",
  "kodepoli": "INT",                  = rsmst_polis.kd_poli_bpjs
  "norm": "",                         boleh kosong — diisi dari rsmst_pasiens.reg_no
  "tanggalperiksa": "2026-10-23",     Y-m-d, maks. H+35
  "kodedokter": 12345,                = rsmst_doctors.kd_dr_bpjs
  "jampraktek": "08:00-12:00",
  "jeniskunjungan": 1,                1 rujukan FKTP · 2 rujukan internal · 3 kontrol · 4 rujukan antar RS
  "nomorreferensi": "0123R0011025..." wajib kecuali kontrol
}

→ 200 { "response": { "nomorantrean": "INT-5", "angkaantrean": 5,
        "kodebooking": "20261022143000JKN", "norm": "123456",
        "namapoli": "...", "namadokter": "...", "estimasidilayani": 1761190200000,
        "sisakuotajkn": 12, "kuotajkn": 30, "sisakuotanonjkn": 12, "kuotanonjkn": 30,
        "keterangan": "..." }, "metadata": { "message": "Ok", "code": 200 } }
TXT,

'kuota' => <<<'TXT'
kuota        = scview_scpolis.kuota          (jadwal dokter per poli per hari)

terisi       = pendaftaran RJ hari itu        rsview_rjkasir, rj_status <> 'F'
             + booking JKN belum checkin     referensi_mobilejkn_bpjs, status 'Belum',
                                             belum punya baris rstxn_rjhdrs

sisa kuota   = kuota - terisi                → 0 = booking DITOLAK

nomor antrean berikutnya =
    MAX( max(rstxn_rjhdrs.no_antrian), max(to_number(referensi.angkaantrean)) ) + 1
    dihitung DI DALAM Cache::lock per dokter + tanggal (15 detik)
TXT,

'checkin' => <<<'TXT'
POST https://apijkn.rsimadinah.com/checkinantrean
{ "kodebooking": "20261022143000JKN", "waktu": 1761195600000 }   waktu = milidetik

Syarat lolos:
  • tanggalperiksa = hari ini
  • status bukan 'Batal' dan belum 'Checkin'
  • jam checkin: 1 jam sebelum jam mulai praktik s/d jam selesai
  • jadwal dokter-poli masih ada di scview_scpolis

Yang terjadi:
  1. rstxn_rjhdrs baru  (rj_no = MAX+1, no_antrian = angka booking,
     rj_status 'A', erm_status 'A', klaim_id 'JM', shift dari rstxn_shiftctls)
  2. referensi_mobilejkn_bpjs.status → 'Checkin'
  3. push ke BPJS: antrean/add, lalu taskId 3 (antrean/updatewaktu)
  4. hasil push + waktu taskId 3 dicatat di datadaftarpolirj_json
TXT,

'apache' => <<<'TXT'
# /etc/apache2/sites-available/sirus.conf — server asal (origin) di balik Cloudflare.
# HTTPS dipasang di Cloudflare; Apache cukup HTTP :80 (Cloudflare Tunnel / mode SSL Flexible).
# SATU VirtualHost untuk dua host — Laravel yang memilah SIRUS vs API.
<VirtualHost *:80>
    ServerName  dev-sirus.rsimadinah.com
    ServerAlias apijkn.rsimadinah.com

    DocumentRoot /var/www/html/sirus-php82/public
    <Directory /var/www/html/sirus-php82/public>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.4-fpm-sirus.sock|fcgi://localhost"
    </FilesMatch>
    LimitRequestBody 41943040
    ErrorLog  ${APACHE_LOG_DIR}/sirus-error.log
    CustomLog ${APACHE_LOG_DIR}/sirus-access.log combined
</VirtualHost>

# Bila Cloudflare memakai mode SSL "Full (strict)" (tanpa tunnel): salin VirtualHost di atas
# menjadi <VirtualHost *:443>, tambahkan SSLEngine on + Origin Certificate Cloudflare
# (satu sertifikat bisa mencakup dev-sirus.rsimadinah.com dan apijkn.rsimadinah.com):
#   SSLCertificateFile    /etc/ssl/cloudflare/origin.pem
#   SSLCertificateKeyFile /etc/ssl/cloudflare/origin.key

sudo a2enmod proxy_fcgi setenvif rewrite headers ssl
sudo a2ensite sirus && sudo apache2ctl configtest && sudo systemctl reload apache2
TXT,

'cloudflare' => <<<'TXT'
1. Cloudflare — kedua host menuju server asal yang sama
     Tunnel  : Public Hostname dev-sirus.rsimadinah.com → http://localhost:80
               Public Hostname apijkn.rsimadinah.com    → http://localhost:80
     Proxied : record DNS kedua host → IP server yang sama (awan oranye)
   JANGAN isi "HTTP Host Header" di setelan tunnel — Laravel memilah dari host asli.

2. .env di server
     APP_URL=https://dev-sirus.rsimadinah.com
     MOBILE_JKN_HOST=apijkn.rsimadinah.com
   php artisan config:clear

3. IP asli pemanggil
   trustProxies sudah aktif di bootstrap/app.php → request()->ip() memakai X-Forwarded-For
   dari Cloudflare, jadi batas 60 request/menit dihitung per IP BPJS, bukan per IP Cloudflare.

4. URL yang terdaftar di BPJS tetap: https://apijkn.rsimadinah.com/  (path di root)

5. Cek cepat dari luar
     https://apijkn.rsimadinah.com/login      → 404   (SIRUS tertutup di host API)
     https://dev-sirus.rsimadinah.com/auth    → 404   (API tidak ada di host SIRUS)
     HTTP 530 dari Cloudflare                 → server asal / tunnel mati, bukan masalah Laravel
TXT,

'perjalanan' => <<<'TXT'
Request BPJS:  POST https://apijkn.rsimadinah.com/ambilantrean

1. Apache (VirtualHost SIRUS)           semua host masuk ke public/index.php
2. bootstrap/app.php → withRouting      then: Route::domain(MOBILE_JKN_HOST)
                                          ← nilai dari config/services.php ('mobile_jkn.host')
                                          ← yang membaca .env MOBILE_JKN_HOST
3. routes/mobile-jkn.php                /ambilantrean → AntrolBPJSController@ambilantrean
                                          middleware: api + throttle:mobile-jkn (60/menit/IP)
4. Api/MobileJkn/AntrolBPJSController   authenticate() → validasi → kuota → Cache::lock → insert
     ├─ Traits/MobileJkn/TraitJWTRsiMadinah   token, sendResponse/sendError + log api_log_status
     ├─ Traits/MobileJkn/AntrianTrait         panggilan keluar ke Antrean RS BPJS
     └─ Traits/MobileJkn/EmrRJTrait           tulis datadaftarpolirj_json saat checkin
5. Error tak tertangkap                  bootstrap/app.php → withExceptions: JSON {error, message}

Request ke halaman SIRUS lewat host API (mis. /login):
   routes/web.php → grup web → BatasiHostMobileJkn (paling depan) → 404
TXT,

'masalah' => <<<'TXT'
Gejala dari BPJS / pasien                   Periksa
─────────────────────────────────────────── ───────────────────────────────────────────
HTTP 530 (halaman Cloudflare)               server asal mati / Cloudflare Tunnel putus — cek Apache,
                                            php8.4-fpm, cloudflared di server; bukan masalah kode
Semua endpoint 404                          MOBILE_JKN_HOST di .env = host yang dipanggil?
                                            php artisan config:clear setelah ubah .env
Halaman SIRUS muncul di host API            tidak mungkin: middleware BatasiHostMobileJkn → 404
"Unauthorized (Username dan Password Salah)" user BPJS di tabel users, password benar
"Unauthorized: User not found"              x-username tidak ada di tabel users
429 Too Many Requests                       > 60 request/menit dari 1 IP (throttle mobile-jkn)
"... termasuk Pasien Baru di RSI Madinah"   nokartu_bpjs belum ada di rsmst_pasiens → daftar offline
"NIK anda ... berbeda"                      rsmst_pasiens.nik_bpjs ≠ NIK di BPJS → perbaiki di Master Pasien
Kuota habis padahal poli sepi               booking 'Belum' yang tidak datang ikut dihitung;
                                            batalkan lewat menu Booking Rawat Jalan
"Lakukan checkin minimal 1 jam sebelum ..." checkin terlalu pagi; buka 1 jam sebelum jam mulai praktik
"Tanggal periksa bukan hari ini ..."        checkin hanya pada tanggal periksa
Antrean tidak muncul di dashboard BPJS      Log BPJS: antrean/add & updatewaktu gagal? cek ANTRIAN_*
Error 500                                   storage/logs/laravel.log + tabel api_log_status
TXT,

'uji' => <<<'TXT'
# Dari server (ganti host & kredensial). Endpoint MENULIS ke api_log_status —
# lakukan di server yang memang dipakai, bukan dari laptop yang .env-nya ke DB produksi.

curl -s https://apijkn.rsimadinah.com/auth -H 'x-username: <user>' -H 'x-password: <pass>'

curl -s -X POST https://apijkn.rsimadinah.com/statusantrean \
  -H 'x-username: <user>' -H 'x-token: <token>' -H 'Content-Type: application/json' \
  -d '{"kodepoli":"INT","kodedokter":12345,"tanggalperiksa":"2026-10-23","jampraktek":"08:00-12:00"}'

# Host SIRUS tidak boleh melayani API, host API tidak boleh melayani SIRUS:
curl -s -o /dev/null -w '%{http_code}\n' https://apijkn.rsimadinah.com/login    # 404
curl -s -o /dev/null -w '%{http_code}\n' https://dev-sirus.rsimadinah.com/auth    # 404
TXT,

        ];
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snippet = $this->snippets();

        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Apa itu Mobile JKN',
                'arsitektur'  => 'Arsitektur & Host',
                'alur-pasien' => 'Alur Pasien',
                'peta-file'   => 'Peta File',
            ],
            'Cara Kerja' => [
                'autentikasi'  => 'Autentikasi',
                'ambil-antrean' => 'Ambil Antrean',
                'checkin'      => 'Checkin',
                'endpoint-lain' => 'Batal, Status, Sisa, Operasi',
            ],
            'Operasional' => [
                'di-sirus'    => 'Di Layar SIRUS',
                'setup'       => 'Setup & Deploy',
                'masalah'     => 'Masalah & Penanganan',
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
                    <a href="{{ route('panduan-dev') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Panduan Mobile JKN</span>
                </div>
                <x-theme-toggle />
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
                            Kode: <span class="ds-code">Api\MobileJkn\AntrolBPJSController</span><br>
                            Dokumen: <span class="ds-code">docs/mobile-jkn-api.md</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.mobile-jkn.mobile-jkn-mulai')

                    @include('pages.panduan-dev.mobile-jkn.mobile-jkn-cara-kerja')

                    @include('pages.panduan-dev.mobile-jkn.mobile-jkn-operasional')

                    {{-- ============ NAVIGASI PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-16 pt-8"
                        style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-ghost"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">
                            ← <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])">
                            <span x-text="labels[order[idx() + 1]]"></span> →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
