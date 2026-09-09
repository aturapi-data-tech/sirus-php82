# BPJS: whitelist IP publik & proxy keluar lewat VPS

**Kebijakan BPJS (Sep 2026, Formulir Pengajuan Akses Bridging SIM):** API BPJS hanya melayani
permintaan dari IP publik yang didaftarkan faskes. Yang didaftarkan RSI Madinah (PPK `0184R006`,
vendor Atur Rapi Data Technology, aplikasi siRUS) adalah IP **VPS** — bukan IP kantor RS. Maka
semua panggilan API BPJS dari siRUS harus KELUAR lewat VPS itu.

Cara yang dipilih: **forward proxy (Squid) di VPS**, aplikasi cukup diberi `BPJS_PROXY_URL`.
Tidak perlu VPN, tidak perlu memindah server, tidak mengubah URL BPJS satu pun.

## 1. Sisi aplikasi (sudah di repo)

| Bagian | Isi |
|---|---|
| `config/bpjs.php` | `proxy_url`, `ip_whitelist`, `timeout`, `connect_timeout`, `ip_echo_url` — semua dari `.env` |
| `App\Support\Bpjs\BpjsHttp::mulai()` | satu-satunya pembuat `PendingRequest` untuk API BPJS: batas waktu + `proxy` Guzzle bila `BPJS_PROXY_URL` terisi |
| Trait `VclaimTrait`, `AntrianTrait`, `AplicaresTrait`, `iCareTrait` (+ `SisruteTrait`, `ApotekTrait` di branch fitur) | `Http::timeout(8)->connectTimeout(3)` diganti `BpjsHttp::mulai()` |
| `php artisan bpjs:cek-proxy` | bertanya ke `api.ipify.org` lewat jalur yang sama, lalu membandingkan dengan `BPJS_IP_WHITELIST` |

`.env`:

```
BPJS_PROXY_AKTIF=true                      # SAKLAR; bawaan false = langsung (keadaan produksi saat ini)
BPJS_PROXY_URL=http://sirus:KATA_SANDI@38.103.170.232:3128
BPJS_IP_WHITELIST=38.103.170.232
# BPJS_HTTP_TIMEOUT=8
# BPJS_HTTP_CONNECT_TIMEOUT=3
```

Proxy hanya dipakai bila `BPJS_PROXY_AKTIF=true` **dan** URL terisi. Produksi boleh menyimpan URL
sejak sekarang dengan saklar mati; saat BPJS menegakkan whitelist, cukup `BPJS_PROXY_AKTIF=true` +
`php artisan config:clear`. Mematikan kembali = satu baris juga. SATUSEHAT, Mindray, dan
layanan lain TIDAK lewat proxy ini.

Aturan pengembangan: panggilan BPJS baru **wajib** lewat `BpjsHttp::mulai()`, jangan `Http::` langsung —
kalau tidak, panggilan itu keluar dari IP RS dan ditolak BPJS tanpa pesan yang jelas.

## 2. Sisi VPS (Rocky 8, sekali pasang) — DIPASANG 09/09/2026, mode bebas lokasi

Tutorial langkah demi langkah + hasil nyata: halaman **/panduan-dev/bpjs-proxy**. Ringkasnya, sebagai root
di VPS. Mode yang dipasang: **cukup user+sandi** (tanpa kunci IP asal) supaya dev dari mana pun bisa
memakai; kunci ke IP RS bisa ditambah belakangan (§4).

```bash
dnf install -y squid httpd-tools
htpasswd -c /etc/squid/passwd sirus          # buat user proxy + kata sandi

chgrp squid /etc/squid/passwd && chmod 640 /etc/squid/passwd
cp /etc/squid/squid.conf /etc/squid/squid.conf.asli
cat > /etc/squid/squid.conf <<'EOF'
http_port 3128

# Lapis 1: user + sandi (/etc/squid/passwd)
auth_param basic program /usr/lib64/squid/basic_ncsa_auth /etc/squid/passwd
auth_param basic realm proxy-bpjs
acl terautentikasi proxy_auth REQUIRED

# Lapis 2: tujuan yang boleh — hanya BPJS + penunjuk IP untuk uji
acl bpjs dstdomain .bpjs-kesehatan.go.id
acl ipecho dstdomain api.ipify.org
acl port_aman port 443 80

http_access allow terautentikasi bpjs port_aman
http_access allow terautentikasi ipecho port_aman
http_access deny all

# Tanpa cache, tanpa jejak identitas
cache deny all
via off
forwarded_for delete
request_header_access X-Forwarded-For deny all
EOF

squid -k parse 2>&1 | grep -i "error\|fatal"; echo "parse selesai"
systemctl enable --now squid
systemctl disable --now rpcbind rpcbind.socket     # port 111 bawaan, tidak dipakai
# VPS ini tanpa firewalld; bila ada: firewall-cmd --permanent --add-port=3128/tcp && firewall-cmd --reload
```

Hasil nyata 09/09/2026: tanpa sandi 407; ipify lewat proxy = 38.103.170.232; Google/neverssl 403;
apijkn (produksi) HTTP 400 dalam 0,15 dtk (tembus); apijkn-dev habis waktu dari mana pun (host dev mati).

Uji dari server siRUS:

```bash
curl -x http://sirus:KATA_SANDI@38.103.170.232:3128 https://api.ipify.org   # harus mencetak 38.103.170.232
php artisan bpjs:cek-proxy                                                 # harus "COCOK"
```

## 3. Keamanan VPS — kerjakan hari pertama

- Kata sandi root VPS sempat dibagikan di percakapan → **ganti sekarang** (`passwd`), lalu
  matikan login sandi SSH (`PasswordAuthentication no`, pakai kunci SSH).
- Port yang terbuka cukup 22 dan 3128; 3128 pun hanya berguna dari `IP_RS` karena ACL.
- Jangan simpan kata sandi proxy di repo; hanya di `.env` server.

## 4. Mengunci ke IP RS (opsional, saat produksi mapan)

Tambah `acl rs src IP_RS/32` dan sisipkan `rs` ke dua baris `http_access allow`, lalu `systemctl reload squid`.
Dev di luar RS akan ditolak 403 walau sandi benar. Kalau IP RS berubah: ubah satu baris + reload; aplikasi tidak disentuh.

## 5. Jejak keputusan

- Formulir diajukan 09/09/2026 a.n. Nuur Wahid Anshary (pemohon & vendor), IP utama 38.103.170.232,
  tanpa IP backup. Bila kelak ada IP backup, cukup tambah `acl` kedua atau VPS kedua + ubah `.env`.
- Alternatif yang ditolak: memindah seluruh siRUS ke VPS (Oracle on-prem), VPN site-to-site
  (butuh perangkat di RS), reverse proxy per-URL BPJS (header/host BPJS berubah).
