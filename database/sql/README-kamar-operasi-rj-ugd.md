# Skrip DDL — Kamar Operasi untuk RJ & UGD

Kode modul Kamar Operasi **membutuhkan** kelima skrip di bawah. Bila kode naik ke
sebuah environment sebelum skripnya dijalankan di sana, halamannya tidak sekadar
kehilangan fitur — query menyebut kolom secara eksplisit, jadi kolom yang belum ada
melempar **ORA-00904** dan halaman rusak.

Jalankan **berurutan**; tiap berkas punya blok verifikasi di bagian akhir.

| # | Berkas | Isi | Dev | Produksi |
|---|---|---|---|---|
| 1 | `2026_07_31_alter_kamar_operasi_rj_ugd.sql` | `rstxn_oks.rihdr_no` jadi nullable + kolom `status_rjri`/`ref_no`; tabel biaya `rstxn_rjoks` & `rstxn_ugdoks` | ✅ 2026-07-31 | ❌ |
| 2 | `2026_07_31_alter_tempadmins_kolom_ok.sql` | Kolom `ok` di `rstxn_ugdtempadmins` & `rstxn_ritempadmins` (tanpa ini biaya operasi hilang saat transfer antar-unit) | ✅ 2026-07-31 | ❌ |
| 3 | `2026_07_31_view_docsalaries_ok_rj_ugd.sql` | `RSVIEW_NEWDOCSALARIES` — jasa dokter operator & anestesi dari operasi RJ/UGD | ✅ 2026-07-31 | ❌ |
| 4 | `2026_08_01_view_kwitansi_rj_ugd_operasi.sql` | `RSVIEW_RJSTRS` & `RSVIEW_UGDSTRS` — pos Kamar Operasi di kwitansi | ✅ 2026-08-01 | ❌ |
| 5 | `2026_08_01_view_tkview_accounts_ok_rj_ugd.sql` | `TKVIEW_ACCOUNTS` — jurnal pendapatan operasi RJ/UGD (11 pos, akun 4.1F01..4.1F11) | ✅ 2026-08-01 | ❌ |

Catatan Oracle: DDL **auto-commit** — tidak bisa di-rollback. Untuk keempat view,
ambil dulu definisi lamanya sebagai cadangan sebelum `CREATE OR REPLACE`:

```sql
SELECT text FROM user_views WHERE view_name = 'TKVIEW_ACCOUNTS';
```

Perbarui kolom Produksi di tabel ini setelah dijalankan di server.
