# Arsip — berkas SQL modul gaji dokter yang sudah digantikan

**Jangan jalankan berkas di folder ini.** Pemasangan modul gaji dokter kini
memakai satu berkas saja:

    database/sql/2026_08_04_install_gaji_dokter.sql

Keempat berkas di sini lahir bertahap selama pengembangan, dijalankan berurutan
di lingkungan dev pada 1–3 Agustus 2026. Isinya sudah menyatu ke berkas pasang
tunggal di atas dalam bentuk finalnya.

| Berkas | Isi |
|---|---|
| `2026_08_01_table_gajidoctors.sql` | struktur awal: kolom parameter di `RSMST_DOCTORS` + dua tabel transaksi |
| `2026_08_01_seed_gajidoctor_params.sql` | parameter 19 dokter, hasil bedah `0726sp.xlsx` |
| `2026_08_02_alter_gajidoctors_lanjutan.sql` | 4 kolom susulan: `npwp`, `nilai_manual`, `potongan_rs_aturan`, `npwp_status` (header) |
| `2026_08_03_alter_doctors_npwp_status.sql` | `npwp_status` di master — membalik keputusan berkas sebelumnya |

## Kenapa disimpan

Berkas 08-02 dan 08-03 saling bertentangan, dan pertentangan itu justru yang
berguna dibaca. Berkas 08-02 sengaja **tidak** membuat kolom status NPWP: status
disimpulkan dari terisi atau tidaknya nomor NPWP, dengan alasan dua sumber
kebenaran untuk satu fakta akan bertengkar suatu saat.

Alasan itu benar secara teori tapi salah di lapangan. Saat kolom `npwp` dipasang,
122 dari 122 dokter nomornya kosong — dan kosong di situ berarti "belum sempat
didata", bukan "tidak punya NPWP". Membaca kekosongan itu sebagai status pajak
membuat seluruh dokter kena PPh 21 +20%: untuk periode Juli 2026 saja potongannya
bertambah Rp1.212.060 dari 36 slip. Berkas 08-03 memisahkan keduanya.

Ringkasan keputusan itu sudah ada di bagian 1 berkas pasang tunggal. Yang tidak
ikut pindah ke sana hanyalah alasan versi pertama — dan itulah yang membuat folder
ini layak disimpan: supaya orang berikutnya yang berpikir "kenapa tidak
disimpulkan dari nomornya saja?" menemukan jawabannya, bukan mengulang percobaan
yang sama.

Kalau riwayat ini dirasa tidak perlu, folder ini aman dihapus seluruhnya.
