# Skrip DDL — Kamar Operasi untuk RJ & UGD

Kode modul Kamar Operasi **membutuhkan** kelima skrip di bawah. Bila kode naik ke sebuah
environment sebelum skripnya dijalankan di sana, halamannya tidak sekadar kehilangan fitur
— query menyebut kolom secara eksplisit, jadi kolom yang belum ada melempar **ORA-00904**
dan halaman rusak.

Jalankan **berurutan**; tiap berkas punya blok verifikasi di bagian akhir.

| # | Berkas | Isi |
|---|---|---|
| 1 | `2026_07_31_alter_kamar_operasi_rj_ugd.sql` | `rstxn_oks.rihdr_no` jadi nullable + kolom `status_rjri`/`ref_no`; tabel biaya `rstxn_rjoks` & `rstxn_ugdoks` |
| 2 | `2026_07_31_alter_tempadmins_kolom_ok.sql` | Kolom `ok` di `rstxn_ugdtempadmins` & `rstxn_ritempadmins` (tanpa ini biaya operasi hilang saat transfer antar-unit) |
| 3 | `2026_07_31_view_docsalaries_ok_rj_ugd.sql` | `RSVIEW_NEWDOCSALARIES` — jasa dokter operator & anestesi dari operasi RJ/UGD |
| 4 | `2026_08_01_view_kwitansi_rj_ugd_operasi.sql` | `RSVIEW_RJSTRS` & `RSVIEW_UGDSTRS` — pos Kamar Operasi di kwitansi |
| 5 | `2026_08_01_view_tkview_accounts_ok_rj_ugd.sql` | `TKVIEW_ACCOUNTS` — jurnal pendapatan operasi RJ/UGD (11 pos, akun 4.1F01..4.1F11) |

## Status

Kelimanya **sudah terpasang** di instance `ORCL` (172.8.8.12, schema `rs`) — diverifikasi
2026-08-01, keempat view berstatus VALID. Bila kode dibawa ke instance Oracle lain,
jalankan di sana juga lalu periksa dengan blok di bawah.

## Cara memeriksa sebuah instance

```sql
-- 1. kolom & tabel
SELECT column_name, nullable FROM user_tab_columns
 WHERE table_name = 'RSTXN_OKS' AND column_name IN ('STATUS_RJRI','REF_NO','RIHDR_NO');
--   harus: STATUS_RJRI ada, REF_NO ada, RIHDR_NO nullable = 'Y'
SELECT table_name FROM user_tables WHERE table_name IN ('RSTXN_RJOKS','RSTXN_UGDOKS');

-- 2. kolom OK di penampung transfer
SELECT table_name FROM user_tab_columns
 WHERE column_name = 'OK' AND table_name IN ('RSTXN_UGDTEMPADMINS','RSTXN_RITEMPADMINS');

-- 3-5. view harus VALID
SELECT object_name, status FROM user_objects
 WHERE object_type = 'VIEW'
   AND object_name IN ('RSVIEW_NEWDOCSALARIES','RSVIEW_RJSTRS','RSVIEW_UGDSTRS','TKVIEW_ACCOUNTS');
```

Untuk memastikan view-nya **versi baru**, bukan sekadar ada: `user_views.text` bertipe LONG
sehingga tidak bisa di-`LIKE` maupun `DBMS_LOB.SUBSTR` (ORA-00997) — ambil teksnya lewat
aplikasi (`SELECT text FROM user_views WHERE view_name = ...`) lalu cari penandanya:

| View | Penanda versi baru |
|---|---|
| `RSVIEW_NEWDOCSALARIES` | `status_rjri` / `ref_no` |
| `TKVIEW_ACCOUNTS` | `status_rjri` / `ref_no` |
| `RSVIEW_RJSTRS` | `rstxn_rjoks` |
| `RSVIEW_UGDSTRS` | `rstxn_ugdoks` |

Jangan memakai `user_dependencies` atas `RSTXN_RJOKS` sebagai penanda untuk dua view
pertama — keduanya membaca `rstxn_oks` langsung, bukan tabel biaya, jadi hasilnya
menyesatkan (versi baru pun akan terbaca "belum terpasang").

## Catatan Oracle

DDL **auto-commit** — tidak bisa di-rollback. Untuk keempat view, ambil dulu definisi
lamanya sebagai cadangan sebelum `CREATE OR REPLACE`:

```sql
SELECT text FROM user_views WHERE view_name = 'TKVIEW_ACCOUNTS';
```
