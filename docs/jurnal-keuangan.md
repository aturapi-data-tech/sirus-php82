# Jurnal keuangan: baca langsung tabel transaksi (tanpa view)

**Keputusan 2026-09-09.** Laporan keuangan (Buku Besar, Cek Saldo Kas, dan laporan berikutnya)
membaca jurnal LANGSUNG dari tabel transaksi, bukan lewat view `TKVIEW_ACCOUNTS` →
`TKVIEW_ACCOUNTS_LABARUGI` → `TKVIEW_ACCOUNTS_NERACA1`.

## Kenapa

- Rantai view ~190 cabang UNION ALL, 26 juta baris: predikat yang tidak sargable memakan >10 menit,
  join di atasnya memicu ORA-04031, dan angkanya di prod menyimpang dari form 6i tanpa bisa
  dilacak (KAS TU 2,2 M vs 27 jt) — sumber selisihnya objek view yang terbaca atau data, bukan rumus.
- Oracle 10.2.0.1 (lokal) bahkan mengembalikan hasil SALAH untuk view ini: agregat berubah
  tergantung bentuk query, dan cabang `OPERATOR (RI1→OK11)` yang definisinya `txn_k = 0` keluar
  bernilai 500 pada 119 baris (bug wrong-result UNION ALL + subquery skalar). Query per cabang
  yang dibangun `Jurnal` konsisten di semua bentuk query dan sama dengan penjumlahan baris di PHP.
- Dengan select langsung, selisih yang tersisa terhadap 6i pasti DATA.

## Komponen

| Berkas | Peran |
|---|---|
| `database/sql/2026_08_01_view_tkview_accounts_ok_rj_ugd.sql` | DDL view — tetap satu-satunya **sumber definisi** cabang jurnal |
| `database/sql/tools/gen-jurnal-cabang.py` | Pembangkit: parse DDL → `JurnalCabang.php`. **Jalankan ulang setiap DDL berubah**, commit hasilnya |
| `app/Support/Keuangan/JurnalCabang.php` | Katalog 188 cabang (dibangkitkan, jangan diedit manual): `name, acc, accK, shift, date, d, k, from, where`; akun konfigurasi ditulis `conf:RJ1` dst. |
| `app/Support/Keuangan/Jurnal.php` | `query($accId, $sisi, $dari, $sampai)` → Builder berbentuk kolom view (`txn_name, txn_acc, txn_acc_k, shift, txn_date, txn_d, txn_k`); `namaAkun()` lookup nama terpisah |
| `app/Support/Keuangan/SaldoKas.php` | Rumus 6i `hitung_saldo_tanggal` (saldo awal tahun + arus, potong shift) di atas `Jurnal` |

## Cara `Jurnal::query` bekerja

1. Muat peta `tkacc_confacctxns` (conf_id → acc_id) sekali per request.
2. Untuk tiap cabang katalog: bila kolom sisi yang diminta (`txn_acc` atau `txn_acc_k`) adalah akun
   konfigurasi dan bukan akun yang diminta → cabang **dibuang di PHP**; bila kolom tabel sumber
   (`a.acc_id`, `acc_id_kas`, …) → diberi predikat `kolom = ?`.
3. Predikat tanggal sargable (`>= TO_DATE .. < TO_DATE + 1`) ditanam di TIAP cabang.
4. Akun konfigurasi di select-list dikirim sebagai binding, bukan subquery skalar.
5. Hasil: untuk akun kas hanya 12 cabang (24 baris cermin) yang benar-benar dikirim ke Oracle.

Konvensi sisi: baris `txn_acc = akun` adalah baris MILIK akun itu (`txn_d`/`txn_k` = debit/kredit
akun itu sendiri). Buku Besar memakai sisi ini untuk akun D maupun K. `SaldoKas::sisi('K')`
masih meniru 6i (baca `txn_acc_k`) — tandanya berlawanan dengan Buku Besar dan **belum
diverifikasi terhadap form 6i**; halaman Cek Saldo Kas hanya memuat akun kas (D) sehingga belum
berdampak. Bereskan sebelum SaldoKas dipakai akun K.

## Aturan

- **Jangan** edit `JurnalCabang.php` manual; ubah DDL view, jalankan pembangkit, commit keduanya.
- **Jangan** join tabel lain di atas hasil `Jurnal::query`; ambil nama akun lewat `Jurnal::namaAkun`.
- Cabang baru di DDL wajib punya filter selektif (EXISTS/status) — lihat catatan di panduan
  koding administrasi; katalog hanya menyalin, tidak memperbaiki.
- Verifikasi setelah regenerasi: bandingkan `Jurnal::query` vs view per akun **pada tingkat baris**
  (bukan agregat, karena agregat view di 10g tidak stabil) untuk rentang tanggal pendek, plus
  `Livewire::test` Buku Besar dan tiga komponen Saldo Kas. Skrip contoh ada di riwayat sesi
  2026-09-09 (`verif.php`, `diag3.php`).
- Laporan baru (laba rugi, neraca) memakai `Jurnal::query` per akun; cabang HPP yang dulu
  ditambahkan di `_LABARUGI` belum ada di katalog — tambahkan ke DDL view dulu bila diperlukan.
