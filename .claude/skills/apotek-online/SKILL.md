---
name: apotek-online
description: Modul Apotek Online BPJS (apotek-rest) — klaim obat PRB/kronis/kemoterapi. Arsitektur ApotekTrait + 4 layar SIMRS, alur klaim, jebakan payload BPJS, sumber data kronis, dan node JSON apotekOnline. WAJIB dibaca sebelum menambah/mengubah kode Apotek Online (app/Http/Traits/BPJS/ApotekTrait, layar /apotek-online, laporan klaim, LOV DPHO) atau saat kiriman ditolak BPJS.
---

# Apotek Online BPJS

Web service BPJS **apotek-rest** untuk **klaim obat PRB / kronis belum stabil / kemoterapi** —
BUKAN pelayanan apotek harian (itu `/transaksi/apotek`). Bridging terpisah dari VClaim.

Dokumentasi web lengkap & mutakhir: **`/panduan-dev/apotek-online`** (halaman memeriksa dirinya
sendiri terhadap trait). Katalog resmi: Trust Mark → Katalog WS → Apotek.

## Status (per 2026-08-16)
- **CID 6323 BELUM aktif** → semua kirim dibalas `Unauthorized! Consumer ID is expired!`.
  URL/nama service/signature sudah TERKONFIRMASI benar (dibuktikan vs ssecd/jkn + nama service
  karangan dibalas HTML gateway, nama benar dibalas JSON BPJS).
- **kode_dpho belum dipetakan** (158 obat kronis, 0 terpetakan) → modal menolak kirim.
- Keduanya di luar kode. Yang murni lokal (worklist, LOV) sudah bisa dipakai.

## Arsitektur
- `app/Http/Traits/BPJS/ApotekTrait.php` — 18 endpoint, meniru VclaimTrait (method `apotek_*`
  standalone). Nama BERAWALAN `apotek` WAJIB: satu komponen bisa pakai ApotekTrait + VclaimTrait
  sekaligus, nama `signature()` yang sama = trait bentrok.
- Env blok sendiri `APOTEK_*` (cons-id apotek didaftarkan TERPISAH dari VClaim).
- 4 layar: Master Obat LOV kode_dpho · Worklist `/apotek-online/rj` · Modal Daftarkan & Kirim ·
  Laporan `/manajemen/rs/apotek-online/laporan-klaim`.

## Alur klaim (5 langkah)
1. `apotek_sep($noSep)` — BUKAN sekadar verifikasi: `response.poli`→POLIRSP, `flagprb`→KDJNSOBAT,
   `noSep`→REFASALSJP. Panggil dulu atau dua field resep cuma tebakan.
2. `apotek_resep_insert()` → `response.noApotik` = **No. SJP apotek**. SIMPAN — dipakai semua
   langkah sesudahnya sebagai NOSJP.
3. `apotek_obat_nonracikan_insert()` / `apotek_obat_racikan_insert()` — satu panggilan per obat.
4. `apotek_pelayanan_daftar($noSjpApotek)` — verifikasi (param SJP apotek, bukan SEP asal).
5. `apotek_monitoring_klaim($bulan,$tahun,$jenis,$status)` — rekap.

## SUMBER OBAT = kronis, bukan e-resep penuh
Klaim hanya untuk **porsi KRONIS**. Sumber: `rstxn_rjobats` WHERE `status_kronis='Y'`.
- `JMLOBT = qty_kronis` (mis. 46/23), **BUKAN** qty resep utuh.
- `qty_bpjs` sudah ditagihkan lewat INA-CBG → mengklaimnya lagi = **klaim ganda**.
- Split kronis RJ hanya NON-RACIKAN (fitur Master Obat Kronis, Mei 2026). Racikan kronis belum ada.

## Jebakan payload (semua berbalas menyesatkan)
- **KDOBAT vs KDOBT** — update stok `KDOBAT`, insert obat `KDOBT`. Beda 1 huruf; salah eja mungkin
  200 tapi stok tak berubah tanpa pesan.
- **SEP alfanumerik 19 char** ("0184R0060726V001670" ada R/V) → `size:19`, BUKAN `digits:19`.
- **Huruf besar/kecil resep** — Hapus Resep huruf KECIL (nosjp/refasalsjp/noresep); Simpan Resep
  huruf BESAR (NORESEP/REFASALSJP). Menyalin nama antar keduanya ditolak.
- **DELETE butuh application/json ASLI**, bukan x-www-form-urlencoded (hapusresep, pelayanan/obat/hapus).
- **NORESEP maks 5 digit & unik per bulan klaim** — panjang dijaga validator, keunikan tanggung
  jawab pemanggil.
- **Urutan param terbalik**: monitoring/klaim = bulan dulu; Prb/rekappeserta = tahun dulu.
  Tertukar tak error, cuma kosong.
- **JNSROBT perekat racikan** — semua bahan satu racikan dikirim terpisah dengan JNSROBT SAMA.
- **JnsTgl** (TGLPELSJP vs TGLRSP) menggeser periode rekap klaim = keputusan akuntansi.
- **Signa tak baku** — e-resep/rjobats tak simpan SIGNA1×SIGNA2 format BPJS. Modal buat EDITABLE +
  tebakan, petugas verifikasi. Jangan tebak diam-diam.
- **Rekap PRB berbaris ganda** (16 baris = 8 unik, tiap baris 2×) → saring di pemanggil.

## kode_dpho (jembatan obat lokal → DPHO)
- Kolom `immst_products.kode_dpho` (bukan di rsmst_listobatbpjses: DPHO sifat OBAT bukan
  kekronisan; Apotek Online juga PRB & kemo).
- Diisi lewat LOV di Master Obat bagian 8, bersumber `apotek_referensi_dpho()` (cache 1 jam).
  **DIPILIH bukan diketik** — salah 1 digit = obat berbeda, tak ditolak saat kirim.
- LOV hidup sebelum API: tampilkan "belum bisa diakses" sampai CID aktif; kegagalan TIDAK di-cache.

## Node JSON: datadaftarpolirj_json.apotekOnline
Menyimpan setup+hasil PENUH (daftar obat, bukan cuma noSjp). Restore ke modal saat dibuka.
`{status: draft|terkirim, noSjp, kdJnsObat, iterasi, noResep, obat[]{jenis,noRacikan,productId,
nama,kodeDpho,signa1,signa2,jml,jho,catatan}, ubahAt/ubahOleh, [kirimAt/kirimOleh/jmlObatTerkirim
bila terkirim]}`. Nama key obat = bentuk MODAL (bukan KDOBT/SIGNA1OBT) supaya restore nyambung;
konversi ke nama BPJS saat kirim().

## Menguji tanpa BPJS
`Http::fake([...])` per URL (`*sjpresep/v3/insert*` dst.) balas metaData.code=200. Rakit payload &
uji rantai penuh + persistence JSON tanpa menyentuh BPJS. Lihat pola di scratchpad uji sesi ini.

Terkait: [[project_master_obat_kronis]], [[project_apotek_online_bpjs]], skill `bpjs-antrean-task-id`.
