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
| `app/Support/Keuangan/JurnalCabang.php` | **Sumber kebenaran** 200 cabang jurnal, dirawat langsung di PHP: `sumber, label, akun, akunLawan, shift, tanggal, debit, kredit, from, where`; akun konfigurasi ditulis `conf:RJ1` dst. Diturunkan 2026-09-09 dari DDL view TKVIEW_ACCOUNTS (188, sumber `ACCOUNTS`) + cabang HPP TKVIEW_ACCOUNTS_LABARUGI (12, sumber `LABARUGI`); sejak itu view hanya untuk form 6i |
| `database/sql/2026_08_01_view_tkview_accounts_ok_rj_ugd.sql` | Skrip migrasi DDL `TKVIEW_ACCOUNTS` (kamar operasi RJ/UGD, sudah dijalankan di prod) — referensi sejarah, bukan sumber katalog |
| `app/Support/Keuangan/Jurnal.php` | `query($accId, $sisi, $dari, $sampai)` → Builder berbentuk kolom view (`txn_name, txn_acc, txn_acc_k, shift, txn_date, txn_d, txn_k`); `queryBanyak([...])` untuk sekumpulan akun; `arusPerAkun([...], $dari, $sampai)` → `[acc_id => [debit, kredit]]` satu pemindaian untuk laporan ber-template; `namaAkun()` lookup nama terpisah |
| `app/Support/Keuangan/SaldoKas.php` | Rumus 6i `hitung_saldo_tanggal` (saldo awal tahun + arus, potong shift) di atas `Jurnal` |

## Pemakai

| Halaman | Cara pakai |
|---|---|
| Cek Saldo Kas | `SaldoKas::hitung` (sisi 6i, potong shift) |
| Buku Besar | `Jurnal::query($acc, SISI_ACC, …)` per akun; nama lawan `namaAkun()` |
| Laba Rugi | template `tkacc_temlabarugineracahdrs` (status L: `L1` = form 6i, `LWEB` = ringkas web) → `_l2s` → `_l1s` → `_dtls` → `tkacc_temaccountes`; nilai `Jurnal::arusPerAkun` bulan & YTD; tanda pos = `dk_status` grup akun (`tkacc_gr_accountses`) baris DTL, **bukan** `acc_dk_status` master yang sering kosong; laba = Σ pendapatan − Σ beban |
| Neraca | **belum dipindah** — masih baca `tkview_accounts` + `temp_id` di tabel DTL yang tidak ada (ORA-00904); template `N1`/`NWEB`; `TKVIEW_ACCOUNTS_NERACA2` menambah baris "LABARUGI BERJALAN" (Σ gra 4/5 status L per tahun ke akun `conf:LRB`) — perlu ditiru saat porting |

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

- Cabang baru/ubah: edit `JurnalCabang.php` (sepasang cabang cermin akun ↔ lawan), dan ubah view di DB
  hanya bila form 6i masih memerlukannya. Uji seperti di bawah.
- **Jangan** join tabel lain di atas hasil `Jurnal::query`; ambil nama akun lewat `Jurnal::namaAkun`.
- Cabang baru wajib punya filter selektif (EXISTS/status) — lihat catatan di panduan koding administrasi.
- Verifikasi setelah mengubah katalog: bandingkan `Jurnal::query` vs view per akun **pada tingkat baris**
  (bukan agregat, karena agregat view di 10g tidak stabil) untuk rentang tanggal pendek, plus
  `Livewire::test` Buku Besar dan tiga komponen Saldo Kas. Skrip contoh ada di riwayat sesi
  2026-09-09 (`verif.php`, `diag3.php`).
- Laporan ber-template pakai `Jurnal::arusPerAkun` (satu pemindaian per rentang), bukan `query` per akun:
  81 akun template L1 = 4 detik per rentang, per akun akan 2×81 query.
- Cabang HPP sudah di katalog (sumber `LABARUGI`).
- Struktur template lama di komponen (`temp_id` di DTLS, section '1'/'2'/'3') SALAH — tabel DTLS hanya punya
  `temp_idl1`; hirarki sebenarnya HDRS(temp_id) → L2S → L1S → DTLS → TEMACCOUNTES.
