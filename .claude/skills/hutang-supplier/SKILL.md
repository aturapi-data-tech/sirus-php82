---
name: hutang-supplier
description: Arsitektur & jebakan siklus pembelian ke supplier — Penerimaan (Obat dari PBF / Barang Non-Medis), Pembayaran Hutang, Topup/Uang Muka (DP), batal posting & hapus salah bayar — beserta dampaknya ke stok gudang dan jurnal kas. WAJIB dibaca sebelum mengubah apa pun di gudang/penerimaan-*, keuangan/pembayaran-hutang-*, keuangan/topup-supplier-*, atau tabel imtxn_receive*/imtxn_cashout*.
---

# Hutang Supplier: Penerimaan → Hutang → Bayar (Kas / DP)

Dipakai paralel dengan **Oracle Forms 6i**: format baris yang ditulis web HARUS sama dengan Forms.
Medis dan non-medis adalah **kembar**: tabel non-medis bersufiks `non`
(`imtxn_receivehdrsnon`, `imtxn_cashouthdrsnon`, `imtxn_cashouthdrtopupsnon`, …).
Berkas non-medis dibuat dari versi PBF lewat substitusi nama tabel/event/judul. Jaga keduanya tetap
kembar (cek dengan `diff` setelah menormalkan nama).

## Berkas

| Layar | Berkas |
|---|---|
| Obat dari PBF / Barang Non-Medis (penerimaan) | `pages/transaksi/gudang/penerimaan-{medis,non-medis}/⚡*-actions.blade.php` |
| Pembayaran Hutang PBF / Non-Medis | `pages/transaksi/keuangan/pembayaran-hutang-{pbf,non-medis}/` (daftar + `-actions` modal) |
| Topup (DP) Supplier PBF / Non-Medis | `pages/transaksi/keuangan/topup-supplier-{pbf,non-medis}/` |
| Jurnal | `app/Support/Keuangan/JurnalCabang.php` (BAYAR PBF, BAYAR PBF TOPUP, BAYAR DIMUKA PBF, RCV *) |
| Saldo stok | `app/Http/Traits/Stock/StockBalanceTrait.php` (`saldoStok('04', $productId, null, 'medis'|'nonmedis')`) |

## Tabel & arti kolom

| Tabel | Isi |
|---|---|
| `imtxn_receivehdrs` | nota; `rcv_status` A daftar tunggu · H hutang · L lunas · F batal; `check_boxstatus`+`vcount` = pilihan & urutan klik di Pembayaran Hutang (disimpan di DB, pola Forms) |
| `imtxn_receivedtls` | item; total nota = diskon kaskade detail − `rcv_diskon` + PPN (bila `rcv_ppn_status='1'`) + materai, dibulatkan ke int |
| `imtxn_cashouthdrs` | **satu baris = satu pembayaran**. `cashout_value` = bagian kas, `cashout_value_topup` = bagian DP, `acc_id` NOT NULL, `shift`, `cashout_desc` VARCHAR2(100) |
| `imtxn_cashoutdtls` | nota yang ditutup sebuah kas keluar (satu kas keluar bisa menutup BANYAK nota) |
| `imtxn_receivepayments` | nominal per nota per pembayaran (sumber "titipan"/sisa hutang). **Tidak menyimpan `cashout_no`** |
| `imtxn_cashouthdrtopups` | setoran DP (uang muka), tidak terikat nota |

**Sisa hutang nota** = total nota − Σ `receivepayments.rcvp_value`.
**Saldo DP supplier** — SATU rumus di `App\Support\Keuangan\SaldoDpSupplier` (rincian/saldo/rekap), jangan ditulis ulang:
saldo awal tahun terakhir ≤ tahun ini (`TKTXN_SALDOAWALHUTANGS[NON].SAH_VALUE_TOP`, dibuat tutup buku Forms =
saldo awal lalu + setor − pakai) + setor − `cashout_value_topup` sejak 1 Jan tahun itu + **Σ SEMUA opname**
(`TKTXN_SODPSUPPS[NON]`, so_d − so_k). Opname dijumlah semua tahun karena tutup buku Forms tak mengenalnya —
kalau kelak tutup buku DP pindah ke web dan memasukkan opname ke SAH, ubah rumus jadi opname sejak tahun saldo awal.
Oracle 10g: subquery di syarat outer join ditolak (ORA-01799) → tahun saldo awal dihitung di subquery dulu.

**Tutup buku tahunan** (`App\Support\Keuangan\TutupBukuSupplier`, halaman `/keuangan/tutup-buku-supplier`, khusus Admin):
menulis `TKTXN_SALDOAWALHUTANGS[NON]` tahun T+1 dengan rumus PERSIS Forms (terbukti di data 2023–2026):
`SAH_VALUE(T+1) = SAH_VALUE(T) + Σ TKVIEW_RCVHDRS[NON].TOTALALL nota H/L tahun T − Σ (cashout_value + cashout_value_topup) tahun T`;
`SAH_VALUE_TOP(T+1) = SAH_VALUE_TOP(T) + Σ setor DP tahun T − Σ cashout_value_topup tahun T`. Desimal disimpan apa adanya
(Forms tidak membulatkan, mis. 5.060.822,99) — jangan bulatkan per komponen (itu memberi selisih Rp 1). Opname TIDAK
dimasukkan supaya tutup buku Forms & web identik. Boleh diulang; angka lama di `Log::info`. Tidak menyentuh saldo akun Neraca.

**Opname saldo DP** (DDL `docs/ddl-opname-dp-supplier.sql`, seq `sodpsupp_seq`/`sodpsuppnon_seq`): pola opname stok —
saldo sebenarnya + alasan → selisih jadi baris bertanggal SYSDATE; saldo awal & transaksi lama tak diubah;
**TIDAK dijurnal** (keputusan user: fungsi beda, sama dengan opname stok) → saldo DP supplier ≠ akun 1.1A13 sebesar
total opname, disengaja. Admin/Supervisor Tu, selisih 0 ditolak, tak ada hapus opname (jejak koreksi).

## Jurnal: apa yang dibaca

- Stok gudang (`tkview_iostockwhs`/`whsnon`) dan jurnal RCV menghitung nota **H/L saja**, bertanggal `rcv_date`.
  Posting A→H/L = stok masuk; batal H/L→A = stok keluar lagi.
- Kas hanya dari **header** `imtxn_cashouthdrs`: "BAYAR PBF" (`cashout_value > 0`, kas berkurang) dan
  "BAYAR PBF TOPUP" (`cashout_value_topup > 0`, uang muka RCV6 berkurang, kas TIDAK berubah).
  Setoran DP = "BAYAR DIMUKA PBF". `receivepayments` dan `cashoutdtls` TIDAK dibaca jurnal.
- **Non-medis (sejak 2026-11-18, keputusan user):** 6 cabang sumber `WEB` di JurnalCabang —
  "BAYAR NON MEDIS" (kas ↔ hutang RCVNON1), "BAYAR NON MEDIS TOPUP" (uang muka RCV6 ↔ hutang),
  "BAYAR DIMUKA NON MEDIS" (kas ↔ RCV6). Uang muka non-medis memakai akun RCV6 yang sama dengan PBF.
  Cabang ini TIDAK ada di view 6i → Saldo Kas web ≠ ARUS KAS 6i sebesar transaksi non-medis.
  **Penerimaan non-medis (persediaan RCVNON2 / hutang) BELUM dijurnal**: hutang supplier di Neraca
  berkurang oleh bayar non-medis tanpa pasangan kreditnya. Dibereskan bersama pekerjaan Neraca.
  Data lama: menu bayar non-medis praktis tak dipakai (1 bayar 2023); non-medis dulu dibayar lewat Cash Out.

## Aturan yang tidak boleh dilanggar

1. **Jangan pernah menghapus header kas keluar yang juga menutup nota lain.** Bug lama di batal
   penerimaan menghapus header bersama: kas & hutang nota lain hilang dari jurnal sementara
   `receivepayments`-nya tetap. Selalu cek `cashoutdtls` dengan `rcv_no <> nota ini`.
2. **Pasangan `receivepayments` ↔ kas keluar** = `rcv_no` + `to_char(rcvp_date) = to_char(cashout_date)`
   (detik sama). Saat menghapus: satu baris per nota dengan Σ = `cashout_value + cashout_value_topup`.
   Dua pembayaran satu nota di detik yang sama (tanggal diketik sama) → `pilihPasanganBayar()` mencoba
   semua kombinasi dan menerima hanya bila semua kombinasi yang cocok memilih nominal yang sama per nota.
   Kalau tak bisa dipastikan (data lama Forms), **tolak**; jangan menghapus baris yang salah.
   Data lama yang tak berpasangan: receivepayments 2012–2015 (sebelum ada kas keluar), dan 225 kas keluar
   2020–2023 yang ≠ Σ pembayaran nota (Rp ±297 jt, data Forms). Bukan dari web, tapi jangan diasumsikan rapi.
3. **DP tidak dicampur tunai** (pola Forms: 908 baris data, semuanya `cashout_value` NULL +
   `cashout_value_topup`). Sumber dana = Kas ATAU Saldo DP. Untuk DP, `acc_id` diisi akun setoran DP
   terakhir supplier (NOT NULL; jurnal kas mengabaikannya karena `cashout_value` kosong).
4. **Pakai DP**: kunci `immst_suppliers` baris supplier (`lockForUpdate`), hitung ulang saldo di dalam
   transaksi. Hapus salah setor juga mengunci supplier dan ditolak bila saldo DP jadi minus.
5. **Bayar tidak melebihi sisa**, termasuk angsuran awal di penerimaan (tidak ada "kembalian" ke supplier).
   Validasi di layar (merah) DAN di server di dalam transaksi setelah baris dikunci.
6. **Shift** kas keluar = jam dari tanggal transaksi (`rstxn_shiftctls`), tampil dan bisa diubah.
   Jangan dari jam tombol ditekan; Saldo Kas per shift membaca kolom ini.
7. **Batal posting penerimaan (H/L→A)** ditolak bila: nota tahun lalu (saldo awal stok tahun ini sudah
   memuatnya); lebih dari satu pembayaran; kas keluar dipakai nota lain; stok tanpa nota ini minus
   DAN ada `qty_k` di ledger sejak `rcv_date` (barang sudah keluar). Ledger yang sudah minus sejak dulu
   (mis. ALOFAR) tidak menghalangi, karena batal hanya mengembalikan ke keadaan sebelum posting. Yang boleh ikut terhapus hanya SATU
   kas keluar eksklusif milik nota itu.
8. **Hapus nota** hanya status A/F. Event `requestDelete` masih hidup walau tombolnya dikomentari.
9. **Hapus salah bayar** = satu kas keluar UTUH beserta seluruh notanya; nota L → H. Angsuran awal
   (`cashout_desc` diawali `Angsuran Awal`) tidak dihapus di sini, melainkan lewat batal posting.
   Peran: Admin / Supervisor Tu, tahun berjalan. Penerimaan memakai Gate `transaksi.batalPenerimaan` (Admin/Tu).
10. `cashout_desc` maksimal 100 karakter → `mb_substr(..., 0, 100)`.
11. Tidak ada tabel log gudang/keuangan → jejak hapus/batal ditulis `Log::info(...)` (`storage/logs`).

## Pola UI

Mengikuti Pembayaran Piutang Pasien: daftar kiri–kanan, modal dengan shift + dialog konfirmasi
(`pratinjau` array, kosong = tertutup) + riwayat per nota (klik baris) + `x-hapus-button` ber-message
yang menyebut nota lain dan dampak kas/DP. Panel panduan biru "Cara Pakai —" ada di keenam layar.
Perbarui panel itu kalau aturannya berubah.

## Menguji

Oracle lokal (`DB_HOST=localhost`) boleh ditulis. Uji alur penuh dengan `Livewire::test(...)` di dalam
`DB::beginTransaction()` … `DB::rollBack()` (sequence tetap maju, data kembali). Ukur dampak jurnal dengan
`Jurnal::arusPerAkun([kas, conf RCV1, RCV6, RCV2], hari, hari)` sebelum/sesudah tiap langkah. Harapan:
setor DP → kas −, RCV6 +; bayar DP → RCV6 −, hutang −, kas tetap; bayar kas → kas −, hutang −; posting →
persediaan + bruto, hutang + (grand total − bayar awal); setiap hapus/batal membalik tepat. Kasus yang wajib dicoba:
bayar DP lalu hapus (saldo kembali), hapus setor yang terpakai (ditolak), batal posting nota dengan stok
terpakai (ditolak) / kas bersama (ditolak) / tahun lalu (ditolak) / tanpa bayar (berhasil).

## Belum ada

- Riwayat nota yang sudah LUNAS tidak terjangkau dari Pembayaran Hutang (daftar hanya status H).
- Cetak bukti kas keluar ke supplier.
- Jurnal non-medis (ditunda, lihat di atas).
