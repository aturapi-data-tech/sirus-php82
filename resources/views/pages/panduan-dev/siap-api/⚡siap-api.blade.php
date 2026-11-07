<?php

use Livewire\Component;

// Panduan API SIAP (bridging penggajian) — SIRUS sebagai SERVER. Halaman referensi tanpa state form.
// Sumber isi: docs/siap-api.md, routes/api-siap.php, Api\Siap\GajiDokterApiController, Middleware\AuthSiapApi,
// Support\Api\{Jwt,JwtSiap}. Versi CLIENT-nya ada di SIAP: menu Panduan → Bridging SIRUS.
new class extends Component {
    public function snippets(): array
    {
        return [

'arsitektur' => <<<'TXT'
  SIAP (penggajian, LAN RS)                         SIRUS (satu aplikasi, DB Oracle SIRUS)
  ┌──────────────────────────────┐                  ┌───────────────────────────
  │ App\Support\GajiDokterSirus  │  POST /api/siap/token   ──►  Api\Siap\GajiDokterApiController@token
  │  token() → cache 45 menit    │  Bearer <jwt>                 ├─ Auth::validate (tabel users)
  │  segarkanDokter()            │  GET  /api/siap/dokter   ──►  ├─ role 'Api Siap' + active_status
  │  prosesSiap(periode)         │  GET  /api/siap/gaji-dokter/{thn}/{bln}/transaksi ─► RSVIEW_NEWDOCSALARIES
  │   ├ transaksi mentah         │                  │              (GajiDokterLampiran::barisMassal — baris yang
  │   ├ struktur gaji SIAP       │                  │               sama dengan layar Monitoring Jasa Dokter)
  │   └ hitung slip (GajiDokter- │   ◄── JSON ──    │  middleware: throttle:siap-api (120/mnt)
  │     Hitung) → snapshot       │                  │              AuthSiapApi (JWT + akun)
  │     BERVERSI siaptxn_gdok_*  │                  │
  └──────────────────────────────┘                  └───────────────────────────

  Tidak ada endpoint tulis. SIRUS tidak tahu-menahu apa yang SIAP simpan.
  Slip TIDAK dihitung di SIRUS: tabel slip & kolom struktur gaji SIRUS dihapus 2026-11-07
  (database/sql/2026_11_07_drop_gaji_dokter.sql).
TXT,

'banding' => <<<'TXT'
                          Mobile JKN (BPJS → SIRUS)            API SIAP (SIAP → SIRUS)
  ───────────────────────  ──────────────────────────────────  ──────────────────────────────────────
  Siapa pemanggil          server BPJS (internet)               server SIAP (LAN RS)
  Pemisahan URL            host sendiri (MOBILE_JKN_HOST),      prefix /api/siap — di host SIRUS biasa, atau
                           path di root (kontrak BPJS)          host sendiri bila SIAP_API_HOST diisi
  Dimuat bila              MOBILE_JKN_HOST terisi               SIAP_API_JWT_SECRET ≥ 32 karakter
  Kredensial               user SIRUS (header x-username/       user SIRUS ber-role 'Api Siap'
                           x-password → x-token)                (body username/password → Bearer)
  Rahasia JWT              MOBILE_JKN_JWT_SECRET                SIAP_API_JWT_SECRET   (TERPISAH)
  Mesin JWT                App\Support\Api\Jwt                  App\Support\Api\Jwt   (SAMA)
  Umur token               12 jam (exp = durasi)                1 jam (exp = waktu, SIAP_API_TOKEN_TTL)
  Kode galat               201 (konvensi BPJS)                  401 / 403 / 422 / 503 (HTTP biasa)
  Arah data                tulis (booking, checkin, RJ)         BACA SAJA
  Batas request            60 / menit / IP                      120 / menit / IP, token 10 / menit
  Whitelist IP             –                                    SIAP_API_IP_WHITELIST (opsional)
  Log                      api_log_status (tiap request)        tidak ada log khusus (laravel.log bila galat)
TXT,

'env' => <<<'TXT'
# .env SIRUS — API SIAP (penggajian, baca saja). Kosongkan SIAP_API_JWT_SECRET untuk mematikan API.
SIAP_API_HOST=                   # kosong = /api/siap di host SIRUS biasa (ditolak di host Mobile JKN);
                                 # diisi, mis. siap-api.rsimadinah.local = hanya host itu, halaman SIRUS ditutup di sana
SIAP_API_JWT_SECRET=<acak ≥ 32 karakter, mis. openssl rand -hex 32>
SIAP_API_TOKEN_TTL=3600          # detik; umur token
SIAP_API_IP_WHITELIST=           # kosong = semua IP; isi "10.10.1.5,10.10.1.6" = hanya server SIAP
SIAP_API_ROLE="Api Siap"         # nama role akun API (bawaan)

# Rahasia JWT Mobile JKN — TERPISAH dari di atas (kosong = kunci lama di kode, hanya masa peralihan)
MOBILE_JKN_JWT_SECRET=<acak ≥ 32 karakter>

# Akun API dibuat SEKALI di server SIRUS (bukan di .env):
php artisan siap-api:akun siap-gaji
#   → user siap-gaji (email siap-gaji@api.siap.local), role Api Siap, password acak dicetak sekali
#   → salin ke .env SIAP: SIAP_API_USER=siap-gaji  SIAP_API_PASSWORD=<password>
# Jalankan lagi perintah yang sama untuk menyetel ulang password.
php artisan config:clear
TXT,

'token' => <<<'TXT'
# 1) Minta token — username/password = akun users SIRUS ber-role 'Api Siap'
POST https://sirus.rsimadinah.local/api/siap/token
Accept: application/json
Content-Type: application/x-www-form-urlencoded
username=siap-gaji&password=<password>

→ 200 { "token": "eyJhbGciOi…", "token_type": "Bearer", "expires_in": 3600 }
→ 401 { "error": "Username / password API salah, akun nonaktif, atau bukan akun API SIAP." }
→ 503 { "error": "API SIAP tidak aktif (SIAP_API_JWT_SECRET belum diatur di .env)." }
→ 429 lebih dari 10 permintaan token / menit dari satu IP

# 2) Semua endpoint data membawa:
Authorization: Bearer <token>
Accept: application/json

# Isi token (payload JWT HS256, ditandatangani SIAP_API_JWT_SECRET):
{ "iss": "sirus", "aud": "siap", "sub": "siap-gaji", "iat": 1793947629, "exp": 1793951229, "jti": "3f9c…" }
TXT,

'dokter' => <<<'TXT'
GET /api/siap/dokter
→ 200 { "dokter": [
    { "dr_id": "088", "dr_name": "Dr. Deny Mory Aryawan, SpOT", "dr_nik": "3504…", "kd_dr_bpjs": "12345", "aktif": true }, … ] }

Sumber: rsmst_doctors (semua baris, aktif & nonaktif) — dipakai SIAP untuk memetakan dr_id ↔ emp_id.
Field npwp_status / skema_gaji_pokok / gaji_pokok tidak ada lagi: struktur gaji dokter disetel di SIAP.
TXT,

'transaksi' => <<<'TXT'
GET /api/siap/gaji-dokter/2026/07/transaksi             tahun 4 digit, bulan 01–12 = BULAN JASA; semua dokter (±1 MB, ±3 detik)
GET /api/siap/gaji-dokter/2026/07/transaksi?dr_id=085   satu dokter
→ 200 { "tahun_jasa": "2026", "bulan_jasa": "07", "jumlah_dokter": 35, "diambil_pada": "2026-11-07 09:00:00",
  "dokter": [ {
     "dr_id": "085", "dr_name": "dr. Nanda Agus Prasetya, SpOG", "aktif": true,
     "baris": [
        { "komponen": "UP RJ", "group": "RJ", "label": "Uang Periksa Rawat Jalan", "seq": 1, "tgl": "02/07/2026",
          "reg_no": "097289Z", "nama": "BUDI ENDA APRIYANI", "keterangan": "", "klaim": "JKN MANDIRI",
          "klaim_status": "BPJS", "no_sep": "0184R0060726V000223", "txn_no": "670903", "nominal": 50000, "pasien": 1 },
        { "komponen": "VISIT", "group": "RI", "label": "Visite Rawat Inap", "seq": 9, "tgl": "28/06/2026", … } ] } ] }
→ 422 { "error": "Periode harus /{tahun 4 digit}/{bulan 01-12}." }

Semua dokter yang punya baris di RSVIEW_NEWDOCSALARIES pada bulan jasa itu. komponen = DESC_DOC, group = GROUP_DOC
(RJ, RJTRF, UGD, UGDTRF, RI, OK, RJK, RADRJ, RADUGD, RADRI), tgl = TANGGAL LAYANAN — baris rawat inap bisa bertanggal
bulan sebelumnya karena jasanya masuk saat pasien pulang. SIAP menghitung slip dari baris ini (App\Support\GajiDokterHitung)
dengan struktur gaji yang disetel di SIAP; dokter per kapita tanpa transaksi diambil SIAP dari struktur gajinya sendiri.
Sumber: GajiDokterLampiran::barisMassal() — baris yang sama dengan modal Rincian di Monitoring Jasa Dokter SIRUS.
TXT,

'perjalanan' => <<<'TXT'
Request SIAP:  GET https://sirus…/api/siap/gaji-dokter/2026/07/transaksi   Authorization: Bearer <jwt>

1. bootstrap/app.php → withRouting then:   if strlen(SIAP_API_JWT_SECRET) >= 32
                                              [Route::domain(SIAP_API_HOST) bila diisi]->prefix('api/siap')
                                              ->middleware([SubstituteBindings, throttle:siap-api])
2. routes/api-siap.php                     /token (throttle:siap-token) · grup AuthSiapApi: /dokter, /gaji-dokter/{thn}/{bln}/transaksi
3. Middleware\AuthSiapApi                  503 bila secret kosong → 404 bila lewat host Mobile JKN → 403 IP di luar whitelist →
                                           401 token tidak sah / kedaluwarsa (JwtSiap::verifikasi) →
                                           401 akun 'sub' tidak ada / nonaktif / tanpa role 'Api Siap'
4. Api\Siap\GajiDokterApiController        transaksi(): RSVIEW_NEWDOCSALARIES → GajiDokterLampiran::barisMassal → JSON
     ├─ Support\Api\JwtSiap                klaim iss/aud/exp, TTL, secret()
     └─ Support\Api\Jwt                    encode/decode HS256 (mesin bersama dengan Mobile JKN)
5. Galat tak tertangkap                    JSON {message} standar Laravel (Accept: application/json) + laravel.log
TXT,

'uji' => <<<'TXT'
# Dari server SIAP (atau laptop di LAN). Semua endpoint BACA SAJA — aman dijalankan ke produksi.
B=https://sirus.rsimadinah.local/api/siap

# token
T=$(curl -s -X POST $B/token -H 'Accept: application/json' \
      -d 'username=siap-gaji&password=<password>' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')

# master dokter
curl -s $B/dokter -H "Authorization: Bearer $T" -H 'Accept: application/json' | head -c 300

# transaksi satu bulan jasa (semua dokter) + satu dokter
curl -s $B/gaji-dokter/2026/07/transaksi            -H "Authorization: Bearer $T" -H 'Accept: application/json' | head -c 300
curl -s "$B/gaji-dokter/2026/07/transaksi?dr_id=088" -H "Authorization: Bearer $T" -H 'Accept: application/json' | head -c 300

# yang HARUS ditolak
curl -s -o /dev/null -w '%{http_code}\n' $B/dokter                                   # 401 tanpa token
curl -s -o /dev/null -w '%{http_code}\n' -X POST $B/token -d 'username=admin&password=x'   # 401 bukan akun API
TXT,

'masalah' => <<<'TXT'
Gejala di SIAP (toast / Uji sambungan)              Periksa di SIRUS
──────────────────────────────────────────────────  ──────────────────────────────────────────────────
"API SIAP tidak aktif (SIAP_API_JWT_SECRET…)" / 503  .env SIRUS: SIAP_API_JWT_SECRET ≥ 32 karakter, lalu
                                                    php artisan config:clear (route tidak dimuat bila kosong → 404)
HTTP 404 semua endpoint                             secret kosong / < 32 karakter; SIAP_API_URL di SIAP salah host;
                                                    SIAP_API_HOST diisi tapi SIAP memanggil host lain (atau lewat host Mobile JKN)
"Username / password API salah, akun nonaktif,      akun siap-gaji ada? aktif? punya role 'Api Siap'?
 atau bukan akun API SIAP." (401)                   password di .env SIAP = yang dicetak siap-api:akun?
                                                    → jalankan lagi: php artisan siap-api:akun siap-gaji
"Token tidak sah atau kedaluwarsa." (401)           SIAP meminta token baru sendiri (dicoba ulang sekali);
                                                    bila terus: SIAP_API_JWT_SECRET diganti saat token masih di-cache SIAP
"Akun API SIAP tidak aktif atau bukan akun API."    akun dinonaktifkan dari User Control / role dicabut — sengaja?
"IP tidak diizinkan." (403)                         SIAP_API_IP_WHITELIST tidak memuat IP server SIAP
                                                    (di balik proxy: trustProxies aktif → X-Forwarded-For)
429 Too Many Requests                               > 120 request/menit atau > 10 token/menit dari 1 IP
"Periode harus /{tahun}/{bulan}" (422)              bulan jasa tidak valid — bug di sisi SIAP
Slip 0 padahal SIRUS sudah menggaji                 bulan JASA ≠ bulan GAJI: SIAP meminta bulan jasa (Juli), slip
                                                    SIRUS bulan gaji Agustus memang bulan jasa Juli
Angka SIAP beda dengan SIRUS                        SIAP memegang snapshot — Ambil ulang di SIAP membuat versi baru
Login web dengan akun siap-gaji ditolak             memang: akun API ditolak LoginRequest (hanya untuk mesin)
Mobile JKN ikut rusak setelah ubah JWT              MOBILE_JKN_JWT_SECRET & SIAP_API_JWT_SECRET terpisah; token JKN lama
                                                    tetap sah ≤ 12 jam bila MOBILE_JKN_JWT_SECRET baru diisi
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
                'pendahuluan' => 'Apa itu API SIAP',
                'arsitektur'  => 'Arsitektur & URL',
                'peta-file'   => 'Peta File',
            ],
            'Cara Kerja' => [
                'autentikasi' => 'Autentikasi & Akun',
                'endpoint'    => 'Endpoint & Bentuk Data',
                'keamanan'    => 'Pengaman',
            ],
            'Operasional' => [
                'setup'   => 'Setup & Deploy',
                'uji'     => 'Uji Sambungan',
                'masalah' => 'Masalah & Penanganan',
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
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Panduan API SIAP (server)</span>
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
                            Kode: <span class="ds-code">Api\Siap\GajiDokterApiController</span><br>
                            Dokumen: <span class="ds-code">docs/siap-api.md</span><br>
                            Sisi client: SIAP → Panduan → <span class="ds-code">Bridging SIRUS</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.siap-api.siap-api-mulai')

                    @include('pages.panduan-dev.siap-api.siap-api-cara-kerja')

                    @include('pages.panduan-dev.siap-api.siap-api-operasional')

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
