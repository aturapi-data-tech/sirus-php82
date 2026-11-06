# API SIAP — Bridging Penggajian (SIRUS sebagai server)

SIAP (Sistem Informasi Administrasi Pegawai) menarik **slip gaji dokter spesialis** yang dihitung modul Gaji Dokter SIRUS,
per bulan jasa, lewat API **baca saja** ini. SIRUS tidak pernah memanggil SIAP dan tidak tahu apa yang SIAP simpan
(SIAP menyimpannya sebagai snapshot berversi — lihat panduan client di SIAP: Panduan → Bridging SIRUS).

Versi web dokumen ini: menu Sistem → **Panduan API SIAP** (`/panduan-dev/siap-api`).

- Route: `routes/api-siap.php` (prefix `/api/siap`, dimuat di `bootstrap/app.php` bila `SIAP_API_JWT_SECRET` ≥ 32 karakter)
- Controller: `App\Http\Controllers\Api\Siap\GajiDokterApiController`
- Penjaga: `App\Http\Middleware\AuthSiapApi`
- JWT: `App\Support\Api\Jwt` (mesin HS256 bersama dengan Mobile JKN) + `App\Support\Api\JwtSiap` (klaim & rahasia SIAP)
- Akun: `php artisan siap-api:akun` (`App\Console\Commands\BuatAkunApiSiap`)

---

## 1. Arsitektur

```
SIAP (LAN RS)                                  SIRUS
App\Support\GajiDokterSirus   POST /api/siap/token        → token JWT (akun users ber-role 'Api Siap')
  token() cache 45 mnt        GET  /api/siap/dokter       → rsmst_doctors
  ambil(periode)              GET  /api/siap/gaji-dokter/{thn}/{bln}          → rstxn_gajidoctorhdrs + dtls
                              GET  /api/siap/gaji-dokter/{thn}/{bln}/lampiran → GajiDokterLampiran::susun
```

| | Mobile JKN (BPJS → SIRUS) | API SIAP (SIAP → SIRUS) |
|---|---|---|
| Pemisahan URL | host sendiri (`MOBILE_JKN_HOST`), path di root | prefix `/api/siap` di host SIRUS biasa, atau host sendiri bila `SIAP_API_HOST` diisi |
| Dimuat bila | `MOBILE_JKN_HOST` terisi | `SIAP_API_JWT_SECRET` ≥ 32 karakter |
| Kredensial | user SIRUS, header `x-username`/`x-password` → `x-token` | user SIRUS ber-role **Api Siap**, body → `Authorization: Bearer` |
| Rahasia JWT | `MOBILE_JKN_JWT_SECRET` | `SIAP_API_JWT_SECRET` (terpisah) |
| Mesin JWT | `App\Support\Api\Jwt` | sama |
| Umur token | 12 jam (`exp` = durasi) | 1 jam (`SIAP_API_TOKEN_TTL`, `exp` = waktu) |
| Kode galat | 201 (konvensi BPJS) | 401 / 403 / 422 / 429 / 503 |
| Arah data | tulis (booking, checkin) | **baca saja** |
| Batas | 60/menit/IP | 120/menit/IP; token 10/menit/IP |
| Whitelist IP | – | `SIAP_API_IP_WHITELIST` (opsional) |

Host:
- `SIAP_API_HOST` kosong → `/api/siap` di host SIRUS biasa. Karena tanpa `Route::domain()` prefix ini ikut terjawab di
  host mana pun yang mengarah ke SIRUS, `AuthSiapApi` dan endpoint token menjawab **404** bila dipanggil lewat
  `MOBILE_JKN_HOST` (host publik).
- `SIAP_API_HOST` diisi (mis. `siap-api.rsimadinah.local`) → route dimuat dengan `Route::domain()`, host lain 404, dan
  `BatasiHostMobileJkn` menutup halaman web SIRUS di host itu (kecuali nilainya = host `APP_URL`, pengaman salah isi).
  `SIRUS_API_URL` di SIAP harus memakai host ini.

---

## 2. Autentikasi

1. `POST /api/siap/token` body `username`, `password` → `{token, token_type: "Bearer", expires_in}`.
   Dicek: `Auth::validate` (tabel `users`, kolom `name`), `active_status` ≠ '0', `hasRole(config('services.siap.role'))`.
2. Endpoint data: header `Authorization: Bearer <token>`. `AuthSiapApi`: rahasia ada (503) → whitelist IP (403) →
   token sah & belum kedaluwarsa (401) → akun `sub` masih ada, aktif, ber-role (401).
3. Payload token: `{iss: "sirus", aud: "siap", sub: "<username>", iat, exp, jti}`, HS256 dengan `SIAP_API_JWT_SECRET`.

Akun API:

```bash
php artisan siap-api:akun siap-gaji              # buat / setel ulang; password dicetak sekali
php artisan siap-api:akun siap-gaji --password=… # password ditentukan
```

- Role `Api Siap` dibuat otomatis (nama dari `SIAP_API_ROLE`). Perintah menolak user yang sudah punya role lain.
- Akun ditolak login web (`LoginRequest`): "Akun API tidak bisa dipakai untuk masuk ke SIRUS."
- Nonaktifkan / ganti password dari User Control → akses API putus pada request berikutnya (akun dicek tiap request).

---

## 3. Endpoint

### GET /api/siap/dokter
```json
{"dokter":[{"dr_id":"088","dr_name":"Dr. Deny Mory Aryawan, SpOT","dr_nik":"…","kd_dr_bpjs":"…",
            "aktif":true,"npwp_status":"Y","skema_gaji_pokok":"A","gaji_pokok":0}]}
```
Semua baris `rsmst_doctors` (aktif & nonaktif) — untuk pemetaan `dr_id` ↔ `emp_id` di SIAP.

### GET /api/siap/gaji-dokter/{tahun}/{bulan}
`tahun` 4 digit, `bulan` 01–12 = **bulan jasa**. 422 bila tidak valid.
```json
{"tahun_jasa":"2026","bulan_jasa":"07","jumlah":36,"diambil_pada":"2026-11-06 13:29:00",
 "slip":[{"gajidoctor_no":21,"dr_id":"088","dr_name":"…","dr_nik":"…","kd_dr_bpjs":"…",
   "tahun_jasa":"2026","bulan_jasa":"07","tahun_gaji":"2026","bulan_gaji":"08",
   "skema_gaji_pokok":"A","nilai_gaji_pokok":0,"potongan_rs_basis":"T","potongan_rs_persen":10,
   "pph21_persen":2.5,"npwp_status":"Y",
   "jasa_total":13505000,"total_gaji":13505000,"potongan_rs":-1350500,"pph21":-303862,
   "potongan_lain_total":0,"tambahan_total":0,"gaji_diterima":11850638,
   "gaji_status":"D","tanggal_bayar":null,"update_date":"2026-08-05 09:12:31",
   "detail":[{"jenis":"J","kode":"JD RJ","label":"Jasa Dokter Rawat Jalan","keterangan":"",
              "nilai":16075000,"nilai_manual":false,"jumlah_pasien":303,"urutan":1}]}]}
```
Sumber `rstxn_gajidoctorhdrs` + `rstxn_gajidoctordtls` (`jenis` J jasa / P potongan / T tambahan). Angka mentah seperti
di tabel (potongan negatif); `gaji_status` D Draft / F Final apa adanya; label dari `GajiDokter::labelKomponen()`.

### GET /api/siap/gaji-dokter/{tahun}/{bulan}/lampiran[?dr_id=]
```json
{"tahun_jasa":"2026","bulan_jasa":"07","jumlah_dokter":36,
 "lampiran":[{"gajidoctor_no":21,"dr_id":"088","dr_name":"…","grup_kapita":["…"],"angka":{},
   "baris":[{"komponen":"JD RI","group":"…","label":"Jasa Dokter Rawat Inap","tgl":"06/07/2026",
             "reg_no":"112169Z","nama":"…","keterangan":"ANAMNESE RAWAT INAP","klaim":"JKN MANDIRI",
             "klaim_status":"BPJS","no_sep":"…","txn_no":"40071","nominal":20000,"pasien":1,"masuk_slip":true}]}]}
```
Sumber `App\Support\GajiDokter\GajiDokterLampiran::susun()` — sama dengan cetak lampiran slip. `masuk_slip=false` =
grup kapita (dibayar lewat skema lain). Semua dokter ±1 MB / ±3 detik untuk 36 slip.

> Bentuk JSON = kontrak dengan `App\Support\GajiDokterSirus::ambil()` di SIAP. Menambah field aman; mengganti
> nama / arti field harus bersamaan di dua repo.

---

## 4. Konfigurasi

| Key | Default | Fungsi |
|-----|---------|--------|
| `SIAP_API_HOST` | (kosong = host SIRUS biasa) | Host khusus API SIAP, tanpa http:// dan port |
| `SIAP_API_JWT_SECRET` | (kosong = API mati) | Rahasia HS256, ≥ 32 karakter (`openssl rand -hex 32`) |
| `SIAP_API_TOKEN_TTL` | `3600` | Umur token (detik) |
| `SIAP_API_IP_WHITELIST` | (kosong = semua) | Daftar IP server SIAP, dipisah koma |
| `SIAP_API_ROLE` | `Api Siap` | Nama role akun API |
| `MOBILE_JKN_JWT_SECRET` | (kosong = kunci lama di kode) | Rahasia JWT Mobile JKN — terpisah |

Rate limiter di `AppServiceProvider::boot()`: `siap-api` 120/menit/IP, `siap-token` 10/menit/IP.

Urutan deploy: isi `.env` → `php artisan config:clear` → `php artisan siap-api:akun siap-gaji` → salin password ke
`.env` SIAP (`SIRUS_API_URL`, `SIRUS_API_USER`, `SIRUS_API_PASSWORD`) → Uji sambungan dari SIAP → isi whitelist IP.

---

## 5. Uji cepat

```bash
B=https://sirus.rsimadinah.local/api/siap
T=$(curl -s -X POST $B/token -H 'Accept: application/json' -d 'username=siap-gaji&password=…' \
     | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')
curl -s $B/dokter -H "Authorization: Bearer $T" -H 'Accept: application/json' | head -c 300
curl -s $B/gaji-dokter/2026/07 -H "Authorization: Bearer $T" -H 'Accept: application/json' | head -c 300
curl -s -o /dev/null -w '%{http_code}\n' $B/dokter                                        # 401
curl -s -o /dev/null -w '%{http_code}\n' -X POST $B/token -d 'username=admin&password=x'  # 401
```

Regresi minimal setelah mengubah kode: token akun API 200 · akun biasa 401 · akun nonaktif 401 · `/dokter` 200 ·
periode berisi `jumlah` > 0 · bulan 13 → 422 · token Mobile JKN lama masih diterima (`TraitJWTRsiMadinah::cektoken`).

---

## 6. Masalah & penanganan

| Gejala di SIAP | Periksa di SIRUS |
|---|---|
| 503 "API SIAP tidak aktif" / semua 404 | `SIAP_API_JWT_SECRET` ≥ 32 karakter, `config:clear`; `SIRUS_API_URL` di SIAP = host SIRUS (atau `SIAP_API_HOST`), bukan host Mobile JKN |
| 401 "Username / password API salah, akun nonaktif, atau bukan akun API SIAP." | akun ada, aktif, ber-role Api Siap; password = hasil `siap-api:akun` |
| 401 "Token tidak sah atau kedaluwarsa." berulang | rahasia diganti saat token masih di-cache SIAP (SIAP mencoba ulang sekali) |
| 401 "Akun API SIAP tidak aktif atau bukan akun API." | akun dinonaktifkan / role dicabut dari User Control |
| 403 "IP tidak diizinkan." | `SIAP_API_IP_WHITELIST` tidak memuat IP server SIAP (di balik proxy: X-Forwarded-For) |
| 429 | > 120 request/menit atau > 10 token/menit |
| `jumlah` 0 padahal sudah digaji | bulan **jasa** ≠ bulan **gaji** (gaji Agustus = jasa Juli) |
| Angka SIAP ≠ SIRUS | SIAP memegang snapshot; Ambil ulang di SIAP membuat versi baru |
| Mobile JKN ikut terganggu | rahasia terpisah; token JKN lama tetap sah ≤ 12 jam setelah `MOBILE_JKN_JWT_SECRET` diisi |
