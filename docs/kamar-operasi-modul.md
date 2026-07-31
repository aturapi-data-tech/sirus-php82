# Modul Kamar Operasi (OK) — sirus-php82

Port penuh dari Oracle Forms **`rit006x.fmb`** (blok `TRANSAKSI_OK`) ke Laravel/Livewire.
Ditulis sebagai **acuan porting ke RJ dan UGD** — kerjakan bertahap, urutan tahapnya ada di §9.

Terkait: `docs/laborat-architecture.md` (pola satelit penunjang), skill `laborat`,
skill `administrasi-inline-edit`, skill `livewire-input-patterns`, skill `oracle-quirks`.

---

## 1. Konsep

Satelit penunjang yang menempel ke kunjungan induk — sama persis konsepnya dengan
Laboratorium: **ruangan mengirim → petugas OK memproses → biayanya kembali sebagai
baris tagihan di kunjungan induk.**

```
RAWAT INAP                 KAMAR OPERASI                    TAGIHAN RI
  order ─┐
         ▼
   [A] Proses Transaksi ──Trf Biaya-INAP──▶ [L] Selesai ──▶ rstxn_rioks (11 baris)
         │  (bebas edit)                        │
         │                                      └─ Batal Transaksi (L→A, hapus rioks)
         └─ [F] Dibatalkan (warisan, tak diproduksi aplikasi)
```

| Status | Arti | Padanan lab |
|---|---|---|
| `A` | Proses Transaksi — tarif & detail bebas diubah | `C` |
| `L` | Transaksi Selesai — biaya sudah masuk tagihan, terkunci | `H` |
| `F` | Dibatalkan — 180 baris warisan; aplikasi tidak membuat maupun membalikkannya | `F` |

`NVL(ok_status,'A')` — baris ber-status NULL diperlakukan sebagai `A`, mengikuti form legacy.

---

## 2. Model data

Semua tabel **sudah ada** di Oracle dan aktif dipakai sejak 2010.

```
rstxn_rihdrs (kunjungan induk, PK rihdr_no)
      ▲ rihdr_no
      │
rstxn_oks  (PK ok_reg; ok_status, ok_date, dr_id, dr_id_ok, diag_id,
      │     sl_codefrom='01', 11 kolom pos tarif, 3 kolom jasa on call,
      │     4 kolom emp_id crew)
      ├── rstxn_okacts    (PK okact_id; accdoc_id → rsmst_accdocs, okact_price)
      ├── rstxn_okobats   (PK okobat_id; product_id → immst_products, qty, price)
      └── rstxn_okomlops  (PK omlop_dtl; emp_id → hrmst_employees, omlop_fee, oncallomlop_fee)

rstxn_rioks (PK ok_no GLOBAL, FK rihdr_no + ok_reg) ← hasil transfer, 11 pos
```

**PK-nya global dan tanpa sequence** (`ok_reg`, `ok_no`, `okact_id`, `okobat_id`, `omlop_dtl`).
Lihat §6 soal penanganan tabrakan.

---

## 3. Pos tarif — `App\Support\KamarOperasiTarif`

Satu kelas jadi sumber tunggal: daftar pos, tarif baku, pemetaan crew, dan **rumus hitung ulang**.

| Konstanta | Isi |
|---|---|
| `POS` | 11 kolom fee → keterangan yang ditulis ke `rstxn_rioks.ok_desc` |
| `LABEL` | label ringkas untuk layar |
| `POS_TURUNAN_DETAIL` | `oprdoc_fee` (Σ okacts), `equipment_fee` (Σ qty×harga okobats) — tak boleh diketik |
| `POS_GAJI_DOKTER` | `oprdoc_fee`→`dr_id`, `anesdoc_fee`→`dr_id_ok` |
| `POS_ONCALL` | 3 kolom on call — **TIDAK ditagihkan ke pasien** |
| `CREW` | 6 posisi → pos jasa & pos on call miliknya |
| `PERSEN_DARI_OPERATOR` | anestesi 50%, asisten opr/anes & instrument 10% |
| `TARIF_BAKU` | OM LOP 50rb, sewa OK 400rb, perawat 100rb, sewa alat 350rb, pengganti anestesi 0 |

> ⚠️ **Urutan dan teks `POS` tidak boleh diubah.** Laporan lama (kwitansi, pendapatan,
> piutang) mengelompokkan biaya dari string `ok_desc`, bukan dari kode.

### `hitungUlang($okReg, $row)` — satu-satunya tempat rumus ditulis

Dipanggil dari **6 pemicu**: tombol Hitung Tarif OK, tambah/hapus tindakan,
tambah/hapus bahan-alat, dan order dari EMR RI. Kalau rumusnya disalin ke masing-masing,
angka pasien akan berbeda tergantung lewat pintu mana petugas masuk.

Aturannya:
1. `oprdoc_fee` & `equipment_fee` **selalu** dijumlah ulang dari tabel detail.
2. Pos persentase **selalu disegarkan** dari `oprdoc_fee` terkini — persentase itu alat
   bantu hitung, bukan pengunci (keputusan user 2026-07-31).
3. Tarif baku flat **hanya mengisi yang masih NULL** — penyesuaian petugas (mis. OM LOP
   50rb diubah 75rb) tidak boleh kembali ke baku.
4. Total = dijumlah dari nilai yang **benar-benar tersimpan**, bukan dari persentase.

Wajib dipanggil di dalam `DB::transaction` dengan baris `rstxn_oks` sudah `lockForUpdate`.

---

## 3b. Susunan komponen — dipecah per bagian seperti Administrasi RJ

```
penunjang/kamar-operasi/
  ⚡daftar-kamar-operasi.blade.php          worklist + tombol Tambah Operasi
  ⚡daftar-kamar-operasi-actions.blade.php  SHELL modal (605 baris)
  ⚡daftar-kamar-operasi-tambah-actions     buat transaksi baru
  display-pasien-kamar-operasi/             identitas + info transaksi
  crew-jasa-kamar-operasi.blade.php         crew + pos tarif + jasa on call
  tindakan-kamar-operasi.blade.php          tab Tindakan Operasi
  bahan-alat-kamar-operasi.blade.php        tab Bahan dan Alat
  omlop-kamar-operasi.blade.php             tab Crew OM LOP

app/Http/Traits/Txn/Penunjang/KamarOperasiTrait.php   guard bersama
app/Support/KamarOperasiTarif.php                     registry pos + mesin tarif
```

**Shell** hanya memegang identitas, total, status, dan aksi tingkat transaksi
(Hitung Tarif OK, Trf Biaya-INAP, Batal Transaksi). Semula satu file 1.999 baris;
dipecah supaya tiap bagian bisa dibuka & diubah sendiri.

**Kontrak induk–anak** (sama persis dengan Administrasi RJ):
1. Anak menerima `:okReg` dan **memuat datanya sendiri dari DB** — tidak mewarisi
   state induk yang bisa basi. Status kunci pun dibaca ulang tiap `findData()`.
2. Sesudah menulis, anak `dispatch('kamar-operasi.updated')`.
3. Shell & anak lain mendengar event itu untuk menyegarkan diri.
4. Shell meneruskannya ke `refresh-after-kamar-operasi.saved` supaya worklist di
   belakang modal ikut segar — kalau tidak, kolom Total Tarif di sana basi sampai
   modal ditutup.

**`KamarOperasiTrait`** memuat guard yang dipakai SEMUA anak: `isAllowedRoleOk`,
`isAllowedBatalOk`, `kunciBarisOk`, `catatLogOk`, `jalankanDenganRetryOk`,
`statusOk`, `riHdrNoOk`. Jangan disalin per komponen — satu anak bisa ketinggalan
saat aturannya berubah lalu diam-diam melewati penguncian atau audit log.

---

## 4. Dua pintu masuk (meniru lab)

OK **tidak punya tabel order terpisah** seperti `lbtxn_checkuphdrs`. Order langsung
membuat header `rstxn_oks` berstatus `A` — sama dengan yang dibuat petugas OK sendiri.
Pembedanya hanya audit log.

| Pintu | Komponen | Audit log |
|---|---|---|
| Petugas OK | `penunjang/kamar-operasi/⚡daftar-kamar-operasi-tambah-actions` | `Buat transaksi OK No.X` · kategori **ADMIN** |
| Ruangan mengirim | `ri/emr-ri/pemeriksaan-ri/penunjang/kamar-operasi/rm-kamar-operasi-ri-actions` | `Order Kamar Operasi No.X` · kategori **MR** |

Order dari ruangan boleh menyertakan **diagnosa pra-operasi** (`diag_id` — simpan `diag_id`,
BUKAN `icdx`; lihat skill `diagnosa-flow`) dan **rencana tindakan** yang langsung mengisi
`rstxn_okacts` lalu memanggil `hitungUlang()`.

Daftar read-only per kunjungan: `rm-daftar-kamar-operasi-ri`.

---

## 5. Guard pulang DUA ARAH — bagian terpenting

Ini yang membuat modul konsisten, dan **wajib ikut diport ke RJ/UGD**.

**MAJU** — `EmrRITrait::checkOkPendingRI()` dipakai di `kasir-ri.blade.php::postTransaksi()`:
pasien tidak bisa dipulangkan selama ada `rstxn_oks` ber-`NVL(ok_status,'A')='A'`.
Pesannya menyebut nomor OK-nya (`daftarOkPendingRI()`). Urutan guard: lab dulu, baru OK.

**MUNDUR** — begitu kunjungan induk tidak aktif lagi, Batal Transaksi ikut tertutup
(tombol `disabled` + banner). Menghapus biaya dari tagihan yang sudah ditutup membuat
total kwitansi tidak cocok. Ini menyamai aturan Batal Transfer UGD→RI.

> **Kenapa penting:** transfer mensyaratkan `ri_status='I'`. Tanpa guard MAJU, pasien bisa
> pulang duluan dan biaya operasinya terkunci selamanya di luar tagihan. Itulah asal
> **15 transaksi macet status `A`** sejak 2025-02-19 yang ditemukan saat modul ini dibuat.

Kalimat penguncian disusun **per aksi** lewat `pesanTerkunci('batal'|'transfer')` — jangan
satu string untuk dua tombol.

---

## 6. Beda dari legacy — sengaja, jangan dikembalikan

| Legacy `rit006x.fmb` | Di sini | Alasan |
|---|---|---|
| `COMMIT` di tengah proses transfer | seluruh INSERT + UPDATE status **satu** `DB::transaction` | legacy: gagal di pos ke-sekian meninggalkan biaya separuh yang tak bisa dibatalkan |
| `MAX(ok_no)+1..+11` sekali di awal | `MAX+1` per baris + **retry ORA-00001** | `ok_no` PK global; dua petugas transfer bersamaan bertabrakan |
| — | `lockForUpdate` + `lockRIRow` + audit log dalam transaksi yang sama | |
| Pos persentase hanya diisi bila NULL | selalu disegarkan | lihat §3 |
| `EXCEPTION WHEN OTHERS` menelan error | `RuntimeException` → toast, `QueryException` → retry/tampilkan | |

> **`SELECT MAX(...) FOR UPDATE` DITOLAK Oracle** (`ORA-01786`, tidak boleh pada query
> agregat). Mengunci baris induk juga tidak menolong karena PK-nya global lintas kunjungan.
> Solusinya retry, bukan lock.

---

## 7. Dampak ke luar modul

**`oprdoc_fee` dan `anesdoc_fee` menggerakkan pendapatan dokter.** View
`RSVIEW_NEWDOCSALARIES` membaca `RSTXN_OKS` langsung: `SUM(OPRDOC_FEE)` atas `dr_id`
(DESC_DOC `'OPERATOR'`) dan `SUM(ANESDOC_FEE)` atas `dr_id_ok` (`'ANASTESI'`), hanya untuk
kunjungan `ri_status='P'`.

Konsekuensinya: mengubah dua pos itu **atau mengganti dokternya** menggeser tagihan pasien
DAN Laporan Pendapatan Jasa Dokter. Karena itu `dr_id`/`dr_id_ok` tidak boleh dikosongkan,
dan setiap perubahan tarif/crew diaudit ke `userLog` kunjungan induk.

**Istilah di layar** (jangan tertukar):
- `JD Operator` / `JD Anestesi` = nama **pos tarif** (JD = Jasa Dokter)
- badge `Dokter` = penanda pos itu **juga** jadi pendapatan dokter
- "pendapatan dokter" untuk konsep penghasilan — jangan tulis "jasa dokter" di situ

---

## 8. Pola UI yang dipakai (ikut diport)

- **Display pasien** `display-pasien-kamar-operasi` — satu kartu, kiri identitas lengkap
  via `MasterPasienTrait`, kanan info transaksi ringkas. Tema sama dengan display RJ/UGD/RI
  dan `display-pasien-laborat`. Butuh listener `refresh-after-*.saved` karena prop `okReg`
  tidak berubah selama modal terbuka.
- **Susunan modal** meniru Administrasi RJ: header display + kartu total → body 1:1
  (Crew & Jasa | tab detail) → footer aksi.
- **Bingkai per KELOMPOK, bukan per sel**: satu bingkai hijau "Ditagihkan ke pasien"
  (6 crew + pos lainnya), satu bingkai putus-putus "Tidak ditagihkan ke pasien" (on call).
- **Nama crew dipasangkan dengan jasanya** (Dr. Operator ↔ JD Operator) — bukan dua daftar
  terpisah yang harus dicocokkan sendiri.
- **Panel info** "Arti penanda pada tarif" — gaya biru-info standar, default tertutup.
- **Warna** pakai token semantic (`bg-warning-tint`, `text-warning-deep`, `border-warning/30`),
  bukan `amber-*` mentah. Cek dulu kelasnya ada di CSS terbangun sebelum memakai varian
  opacity baru.
- **Tabel di tab** ikut standar Administrasi RJ: pembungkus `rounded-2xl` + `overflow-x-auto`,
  `thead` `text-sm text-gray-600`, `th` `px-4 py-3`, baris hover, `tfoot` Total.
- **Semua input angka** `x-text-input-number` tanpa override kelas. Simpan dipicu hook
  `updatedTarif/updatedOncall/updatedRowsOmlop` karena komponen sinkron lewat `$wire.set`
  saat blur.
  > Jebakan: kalau nilai lama dibandingkan dari array yang ter-bind `wire:model`, isinya
  > sudah nilai BARU saat hook jalan → "tidak berubah" terus dan simpan tak pernah jalan.
  > Baca nilai lama dari DB (kasus `updateOmlopFee`).
- **Fokus otomatis saat modal dibuka** — `openActions()` mengirim `kamar-operasi-fokus`
  ke kotak pencarian tab pertama, **hanya bila `!$isFormLocked`** (mode entry). Pola sama
  dengan `administrasi-rj::openModal()` yang mengirim `focus-lov-jasa-karyawan`.
- **Penjaga anti-rebut fokus** — handler fokus mengabaikan permintaan kalau
  `document.activeElement` sudah berupa `input/select/textarea` (user sedang mengetik),
  dan mencoba 3× (0/150/400ms) karena elemen tujuan bisa belum ter-render. Persis pola
  `jasa-karyawan-rj`.
  > ⚠️ **`blur()` dan penjaga anti-rebut itu SEPASANG.** Saat berpindah tab/field,
  > `document.activeElement` masih kolom asal — juga sebuah `input` — sehingga penjaga
  > membatalkan permintaan fokus yang kita kirim sendiri. Karena itu listener tab
  > memanggil `document.activeElement?.blur()` DULU. Mengambil penjaganya tanpa
  > `blur()`-nya membuat "tab pindah tapi kursor tertinggal" — bug yang butuh tiga
  > putaran untuk ditemukan.
- **Rantai Enter** (skill `livewire-input-patterns` §7):
  - kartu tagihan: helper `enterBerikutnya()` — pindah ke input berikutnya menurut urutan
    DOM. Selektornya `input:not([disabled])` + saring `offsetParent`, **bukan**
    `inputmode=numeric`, supaya kotak pencarian LOV crew tidak terlewati.
  - pilih crew dari LOV → fokus turun ke kolom Jasa miliknya (`ok-jasa-<kolom>`).
  - form tambah: LOV → Enter → kolom angka → Enter → simpan → fokus balik ke LOV.
  - **kolom kosong + Enter = "selesai di sini"** → lompat ke tab berikutnya, dan di tab
    terakhir (OM LOP) → fokus tombol **Trf Biaya-INAP**. Jadi seluruh entri bisa
    diselesaikan Enter-Enter dari modal terbuka sampai tombol simpan, tanpa mouse.
    Pola sama dengan Administrasi RJ (`if (!$event.target.value?.trim()) $dispatch(...)`).
  > Jebakan: pergantian tab **harus lewat server**. Enter di kolom angka memicu blur →
  > `$wire.set`, dan respons Livewire me-morph DOM sambil membawa `activeTab` lama sehingga
  > perubahan sisi Alpine ketimpa balik. `lanjutKeTab()` set properti + dispatch
  > `kamar-operasi-tab`; event browser dikirim SETELAH morph, jadi aman.
- Perpindahan fokus lintas komponen lewat satu event `kamar-operasi-fokus` + `ke`, target
  dicari dari `id` wrapper — supaya komponen LOV bersama tidak perlu diubah.
  `lanjutKeTab()` mengirim **dua** event sekaligus: `kamar-operasi-tab` (ganti panel) dan
  `kamar-operasi-fokus` (pindahkan kursor). Mengirim salah satunya saja = tab pindah tanpa
  kursor, atau sebaliknya.

### Beda dari Administrasi RJ (sadar, jangan "diseragamkan" tanpa membaca ini)

| Hal | Administrasi RJ | Kamar Operasi | Alasan |
|---|---|---|---|
| Ganti tab | murni Alpine (`$dispatch('administrasi-rj-goto-tab')`) | method server `lanjutKeTab()` | Enter di OK juga terjadi di `x-text-input-number` yang memicu `$wire.set`; morph-nya menimpa `tab` sisi Alpine |
| Handler fokus | per komponen tujuan (`x-on:focus-<nama>.window` + `$refs`) | satu handler di shell + `document.getElementById` | di RJ tiap tab komponen terpisah sejak awal; di OK shell yang memiliki kontainer tab |
| Nama event fokus | satu event per tujuan (~8) | satu event + payload `ke` | konsekuensi baris di atas |
| Rantai Enter dalam kartu | — | `enterBerikutnya()` urut DOM | tidak ada padanannya di RJ |
| Fokus sesudah pilih crew | — | ke kolom Jasa milik crew itu | tidak ada padanannya di RJ |

Untuk RJ/UGD nanti: baris **ganti tab** WAJIB ikut cara OK (masalahnya sama). Baris
lainnya boleh mengikuti RJ kalau tab-nya dipecah jadi komponen terpisah.

---

## 9. Porting ke RJ / UGD — tahapan

### Temuan awal yang menentukan
Struktur OK **hanya mengenal rawat inap**:
- `rstxn_oks.rihdr_no` FK ke `rstxn_rihdrs` — tidak ada kolom untuk `rj_no`.
- Penampung biaya hanya `rstxn_rioks`; **tidak ada** `rstxn_rjoks` / `rstxn_ugdoks`.
- `sl_codefrom` seluruhnya `'01'` (5.091 baris) — tidak pernah dipakai membedakan layanan.

Jadi porting ke RJ/UGD **bukan sekadar menyalin komponen** — perlu keputusan DDL lebih dulu.

### Tahap 0 — keputusan model data (WAJIB sebelum koding)
Pilihannya:
- **(a) Tabel biaya baru** `rstxn_rjoks` / `rstxn_ugdoks` + kolom referensi baru di
  `rstxn_oks` (mis. `rj_no` + penanda layanan). Paling bersih, paling mirip pola lab
  (`rstxn_rjlabs`/`rstxn_ugdlabs`), tapi butuh DDL di dev **dan** prod.
- **(b) Menumpang `rstxn_rjothers` / `rstxn_ugdothers`** sebagai baris biaya. Tanpa DDL,
  tapi rincian 11 pos hilang jadi satu baris dan laporan lama tak bisa membedakannya.

Ikuti pola lab: `status_rjri` + `ref_no` di header adalah cara repo ini membedakan layanan.
Padanannya di OK berarti menambah kolom penanda + kolom referensi di `rstxn_oks`.

> DDL kolom/tabel baru **wajib dijalankan di tiap environment** — SELECT di modul ini
> menyebut kolom eksplisit, kolom yang belum ada → `ORA-00904`, halaman rusak total.

### Tahap 1 — generalisasi mesin tarif & guard
`KamarOperasiTarif::hitungUlang()` sudah netral layanan. Yang perlu ditambah: pemetaan
layanan → tabel biaya tujuan, sejajar `rstxn_*labs` di lab.

`KamarOperasiTrait` juga netral kecuali `catatLogOk()` yang masih memakai
`lockRIRow`+`appendAdminLogRI`. Untuk RJ/UGD perlu bercabang ke
`lockRJRow`/`appendAdminLogRJ` dan `lockUGDRow`/`appendAdminLogUGD` — pola yang sama
dipakai `logKeParent()` di modul Laboratorium (`⚡daftar-laborat-actions`).

> Karena strukturnya sudah dipecah per bagian (§3b), tiap komponen anak bisa diport
> sendiri-sendiri tanpa menyentuh yang lain — inilah alasan utama pemecahan itu.

### Tahap 2 — guard pulang untuk RJ/UGD
- `checkOkPendingRJ()` / `checkOkPendingUGD()` di `EmrRJTrait`/`EmrUGDTrait`, dipasang di
  kasir RJ/UGD persis seperti `checkOkPendingRI()` di `kasir-ri`.
- Status induk yang mengizinkan transfer: RJ/UGD memakai `rj_status` (`A` aktif), bukan
  `ri_status='I'` — jangan disamakan begitu saja, lihat memory `feedback_ugd_rj_struktur_beda`.

### Tahap 3 — worklist
Tambah filter **Layanan** (RJ/UGD/RI) di `⚡daftar-kamar-operasi`, meniru `filterLayanan`
di `⚡daftar-laborat`. Query induk jadi bercabang per layanan.

### Tahap 4 — order dari EMR RJ & UGD
Klon `rm-kamar-operasi-ri-actions` ke `rj/emr-rj/pemeriksaan/penunjang/` dan
`ugd/emr-ugd/pemeriksaan/penunjang/`, pasang di tab Pelayanan Penunjang masing-masing.
Perhatikan: LOV tarif tindakan RI memakai `lov-jasa-dokter-ri` (harga per kelas kamar) —
RJ/UGD tidak punya kelas kamar, jadi pakai `lov-jasa-dokter` biasa.

### Tahap 5 — administrasi
Tab O.K. read-only di Administrasi RJ/UGD, meniru `o-k-ri.blade.php`.

### Yang belum ada di modul RI (kerjakan sekalian atau catat)
`diag_id_ok` (diagnosa pasca-op), `case_id`, `crew_id_crew*` (LOV `rsmst_okcrews`),
parameter `omlop_jm`/`omlop_person`/`countomlop_crew`, cetak, viewer Rekam Medis,
tautan ke dokumen klinis Laporan Operasi RI, dan penanganan `ok_status='F'`.

---

## 10. Cara verifikasi (dipakai selama modul ini dibangun)

Tidak ada test otomatis. Yang dipakai:
1. `Blade::compileString()` → `php -l` untuk tiap file; pastikan `?>` tepat **1**.
2. `Livewire::test()` lalu hitung keseimbangan tag (`div`, `table`, `tr`, `td`, `span`, …) —
   tag timpang = layout geser.
3. Uji fungsional di dalam `DB::beginTransaction()` … `DB::rollBack()` terhadap data
   produksi nyata, lalu **buktikan data kembali utuh**.
4. Cek kelas Tailwind baru benar-benar ada di `public/build/assets/*.css` sebelum dipakai.
