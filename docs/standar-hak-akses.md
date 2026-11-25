# Standar Hak Akses (Role → Permission → Menu/Aksi)

## Konsep

```
User ──(model_has_roles)──> Role ──(role_has_permissions)──> Permission
```

- **Menu terhubung ke ROLE, bukan ke user.** User mendapat gabungan hak dari semua role-nya.
- Centang diatur per role di **Role Control** (`/database-monitor/role-control`), bentuk matriks
  per **kelompok → program** (sama dengan berkas definisi di bawah).
- **Siapa boleh apa** tersimpan di DB (tabel Spatie, diatur lewat Role Control). **Daftar hak yang
  ada** (menu, aksi, tombol, pintu modal) ditulis di kode, karena penjaganya (`@can`,
  `#[Authorize]`) memang di kode program.
- Dua jenis permission:

| Jenis | Nama | Contoh |
|---|---|---|
| Halaman | `{nama-route}.{lihat\|tambah\|ubah\|hapus}` + tombol khusus `{route}.{kunci}` | `master.poli.hapus`, `rj.pelayanan.bukaAdministrasi` |
| Aksi khusus | nama Gate bebas | `rj.detailKunjungan`, `kasir.rj.batal`, `ri.cppt.ubahSemua` |

- **Lihat** = menu tampil **dan** URL boleh dibuka. Dijaga middleware `akses.halaman`
  (`App\Http\Middleware\CekAksesHalaman`), juga untuk setiap aksi Livewire (persistent middleware).
- **Tambah/Ubah/Hapus/Khusus** = dijaga di komponen (blade + server).
- Aturan pusat: `App\Support\AksesHalaman`. Deny-by-default: route yang tak dikenal ditolak.
- Mode penjaga URL: `AKSES_HALAMAN_MODE=log` (izinkan + catat ke `storage/logs/akses-halaman-*.log`)
  atau `tolak` (403). Role Control & User Control selalu 403 di kedua mode.

## SATU PROGRAM = SATU BLOK (sumber tunggal definisi)

Semua hak satu program ditulis di **satu blok**, berkas per kelompok di `app/Akses/Program/`:

| Berkas | Kelompok |
|---|---|
| `LaporanManajemen.php` | Laporan Pelayanan / Penunjang / SIRS Online / Keuangan |
| `Master.php` | Master Pelayanan / Laboratorium / Apotek / Akuntansi |
| `RawatJalan.php`, `Ugd.php`, `RawatInap.php` | semua program milik jalur itu (pendaftaran, pelayanan, EMR, Modul Dokumen, Bridging, apotek, administrasi & kasir, daftar bulanan, …) |
| `Casemix.php`, `Penunjang.php`, `Gudang.php`, `Keuangan.php`, `Sistem.php` | programnya masing-masing (Penunjang juga memuat **Hasil Penunjang** — per pasien lintas kunjungan) |
| `Bersama.php` | TANPA centang, tak tampil di Role Control: menu halaman gabungan bertab (Antrian Apotek/Kasir) & pintu modal yang dibuka dari >1 jalur |

```php
'Pelayanan Rawat Jalan' => [
    'menu' => [                                   // → sidebar + baris halaman Role Control
        ['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 2, 'route' => 'rj.pelayanan',
         'aksi' => ['lihat'],
         'khusus' => ['bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'dokter', 'perawat']]],
         'title' => 'Pelayanan Rawat Jalan', 'desc' => '…', 'roles' => [...], 'badge' => 'RJ'],
    ],
    'aksiKhusus' => [                             // → Gate; tombol di modal/EMR, posting/batal, dll.
        'rj.detailKunjungan' => ['label' => 'Lihat detail kunjungan', 'roles' => AksiRole::RJ_DETAIL_KUNJUNGAN],
    ],
    'pintu' => [                                  // → listener modal #[Authorize('pintu.…')]
        'emr-rj.rekam-medis.open' => ['rj.pelayanan.bukaRekamMedis'],
    ],
],
```

`App\Support\ProgramAkses` membaca semua berkas itu; `AppMenu::all()`, `AksiRole::khusus()`,
`AksesHalaman::pintu()` dan matriks Role Control semuanya bersumber dari sini. Urutan kelompok
= `ProgramAkses::BERKAS`; urutan program & aksi = urutan tulis di berkas.

**Menaruh sesuatu (keputusan user 2026-11-25): TIDAK ADA hak "lintas jalur".** Hak yang dipakai
di RJ, UGD dan RI dibuat SATU PER JALUR (`rj.dokumen.hapus`, `ugd.dokumen.hapus`, `ri.dokumen.hapus`)
dan masing-masing ditulis di berkas jalurnya, di program yang memakainya (batal kasir RJ →
"Administrasi & Kasir Rawat Jalan", hapus dokumen RJ → "Modul Dokumen Rawat Jalan"). Komponen
bersama yang dipakai ketiga jalur menerima prop/properti jalur lalu mengecek `{jalur}.…`
(contoh `<x-modul-dokumen.aksi-entri jalur="ri">`, Daftar Surat Kontrol `#[Locked] $jalur`).
Pengecualian hanya untuk hak yang memang bukan milik kunjungan/jalur (Hasil Penunjang = per pasien).

**Memecah hak lama** jadi per jalur: entri baru diberi `'menggantikan' => 'nama.lama'` — saat
permission dibuat, centangnya DIWARISKAN dari hak lama (termasuk yang sudah diatur admin), jadi
tak ada yang kehilangan/mendapat hak. Sesudah semua penggantinya dibuat, hak lama dihapus
sendiri oleh sinkron (lihat **Bersih-bersih** di bawah).

**Halaman gabungan bertab** (Antrian Apotek, Antrian Kasir): entri menu ber-`'gabungan' => [route
halaman asal tiap tab]` — tak punya centang sendiri, menu tampil bila boleh salah satunya. Tiap tab
disaring hak halaman asalnya lewat `App\Http\Traits\Concerns\WithTabHakAksesTrait`
(`petaTabHalaman()`, `#[Locked] $activeTab`, `bolehTab()` di `setTab()` & tiap `<x-tab>`). Halaman
bertab yang punya tab miliknya sendiri (Casemix: Rekap iDRG) tetap punya Lihat sendiri; tab
RJ/UGD/RI-nya tetap ikut hak halaman asal.

## Menambah menu / halaman baru

1. Route di dalam grup `Route::middleware(['auth', 'akses.halaman'])` di `routes/web.php`.
2. Entri di `'menu'` blok programnya (program baru = blok baru) — `roles` = **centang awal** saja:
   - `'group'` / `'groupOrder'` / `'order'` = letak di **sidebar** (tak harus sama dengan kelompok
     berkas; mis. Antrian Apotek RJ di sidebar grup "Apotek", definisinya di `RawatJalan.php`).
   - `'aksi' => ['lihat', 'tambah', 'ubah']` — aksi yang berlaku; sisanya `·` di Role Control
     dan tidak dibuat permission-nya. **Tulis eksplisit.**
   - `'rolesAksi' => ['tambah' => [...], 'ubah' => [...]]` — centang awal per aksi bila beda
     dari `roles` (contoh: Daftar RJ/UGD/RI → `AppMenu::ROLES_PENDAFTARAN`).
   - `'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', [roles]]]` — tombol khusus
     halaman. Permission `{route}.{kunci}`. Pakai `@can('rj.pelayanan.bukaAdministrasi')`
     + `#[Authorize(...)]` di method pembukanya.
3. Guard di komponen (lihat pola di bawah).
4. Deploy. Permission baru + centang awalnya dibuat **OTOMATIS** pada request pertama sesudah
   berkas definisi berubah (`App\Support\SinkronAksesOtomatis`, dipanggil middleware
   `akses.halaman`; memantau `app/Akses/Program/*.php`, `AppMenu.php`, `AksiRole.php`,
   `ProgramAkses.php`; tanda di `storage/framework/akses-sinkron.tanda`, hasil di `laravel.log`,
   gagal di log `akses-halaman`). Aman diulang: centang yang dicabut admin tidak kembali.
   `php artisan akses:sinkron` manual tetap bisa (mis. `--dry-run`).

   **Bersih-bersih:** permission yang tak lagi lahir dari definisi (menu/aksi khusus dihapus,
   aksi yang tak ada di `'aksi'` menu, nama lama sumber `menggantikan`, buatan manual) ikut
   DIHAPUS oleh sinkron yang sama, beserta centangnya. Daftar yang dihapus + role-nya dicatat
   di log `akses-halaman` (untuk mengembalikan bila perlu). Aksi yang tak berlaku di suatu
   halaman selalu ditolak (`AksesHalaman::boleh()`), jadi kode tidak bisa bergantung padanya.
   Bagian "Lainnya" di Role Control seharusnya kosong; bila muncul = permission dibuat di luar
   definisi (manual) dan akan hilang di sinkron berikutnya — masukkan ke blok programnya.
5. Admin menyesuaikan centang di Role Control.

Mengoreksi centang awal route/aksi yang permission-nya **sudah** ada:
`php artisan akses:sinkron --ulang=rj.daftar,kasir.rj.batal` — mengisi ulang centangnya dari
definisi (menimpa Role Control untuk itu saja; coba `--dry-run` dulu).

**Aturan seragam kolom aksi (2026-11-25)** — centang yang tak menjaga apa pun menyesatkan admin:

| Tombol | Masuk ke |
|---|---|
| Membuat / mengubah / menghapus data **di halaman itu sendiri** | `'aksi'` Tambah / Ubah / Hapus |
| Membuka modul lain dari halaman (EMR, Administrasi, Modul Dokumen, Telaah) | `'khusus'` → `buka*` |
| Kirim ke pihak luar dari halaman (Aplicares/SIRS, DINKESTA, …) | `'khusus'` → `kirim` |
| Tombol di modal/EMR lintas halaman, posting / batal transaksi | `'aksiKhusus'` blok programnya |
| Identitas profesi (TTD dokter, telaah apoteker) | tetap di kode, bukan centang |

Halaman daftar/antrian yang hanya membuka modul = `'aksi' => ['lihat']` + `'khusus'`. Modal yang
dipakai banyak halaman (Berkas BPJS, Administrasi) dijaga **pintu modal**, bukan kolom halaman.
Modal Tambah/Edit yang juga dipakai untuk melihat/mencetak: jaga `openCreate` + `save()` (baru =
tambah, lama = ubah), JANGAN jaga `openEdit` (contoh: Pelaporan Down Time, penerimaan gudang).

Laporan manajemen = **menu sendiri** (`LaporanManajemen.php`, `'aksi' => ['lihat']`); halaman hub
berisi kartu tautan sudah dihapus (2026-11-25) — jangan membuat hub tautan baru (menu kembar).

`AksesHalaman::INDUK` hanya untuk halaman turunan yang memang tak layak jadi menu (mis. Tutup
Buku Supplier dari Tutup Buku). Lupa didaftarkan = halaman tertutup (di mode `log`: tercatat
`"dikenal": false`).

Sebelum permission dibuat, semua cek memakai cadangan `roles` definisi — deploy tidak mengunci siapa pun.

## Menambah aksi khusus

1. Konstanta role awal di `App\Support\AksiRole` (satu konstanta per maksud, walau isinya
   kebetulan sama dengan aksi lain).
2. Entri di `'aksiKhusus'` blok program yang memakainya:
   `'rj.detailKunjunganKronis' => ['label' => 'Lihat detail kunjungan kronis', 'roles' => AksiRole::RJ_DETAIL_KUNJUNGAN_KRONIS],`
   Label TANPA prefix jalur — baris program di Role Control sudah menyebutnya.
3. `@can('nama')` di blade + `#[Authorize('nama')]` / `->can()` di server (wajib dua lapis).
4. Deploy — Gate terdaftar otomatis (`AppServiceProvider`), permission dibuat otomatis.

Pengecualian: `AksiRole::EMR_PENUNJANG_LIHAT` tetap berbasis role (penanda profesi, bukan hak).

## Pola guard di komponen (acuan: `master-poli`)

```php
use Livewire\Attributes\Authorize;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\Gate;

#[Locked]                                   // klien tak bisa membalik mode utk lolos cek "ubah"
public string $formMode = 'create';

#[On('master.poli.openCreate')]
#[Authorize('master.poli.tambah')]          // berlaku juga utk listener event (bisa dipicu dari console)
public function openCreate(): void { … }

#[On('master.poli.requestDelete')]
#[Authorize('master.poli.hapus')]
public function deleteFromGrid(string $poliId): void { … }

public function save(): void
{
    Gate::authorize($this->formMode === 'create' ? 'master.poli.tambah' : 'master.poli.ubah');
    $this->validate();
    …
}
```

```blade
@can('master.poli.tambah') <x-primary-button wire:click="openCreate">…</x-primary-button> @endcan
@can('master.poli.ubah')   <x-action-edit wire:click="openEdit('{{ $row->poli_id }}')" />  @endcan
@can('master.poli.hapus')  <x-action-delete … />                                            @endcan
```

Wajib dua lapis: `@can` hanya menyembunyikan tombol; `#[Authorize]` / `Gate::authorize()`
yang benar-benar menolak (403).

**Jebakan:** di dalam aksi Livewire `request()->route()` = `/livewire/update`, bukan halaman.
Selalu tulis nama route secara eksplisit di string permission.

## Pintu modal (listener `#[On]`)

Tombol pembuka dijaga di halaman, tapi modal tujuannya mendengar event yang bisa dipicu dari
console browser. Listener modal dijaga dengan **pintu**: kunci `'pintu'` di blok program
memetakan event → semua hak sah pembukanya (lolos bila punya salah satu).

```php
'emr-rj.administrasi.open' => ['rj.pelayanan.bukaAdministrasi', 'rj.antrian-kasir.bukaAdministrasi', …],
'emr-ri.eresep.open'       => ['ri.emr.bukaEresep', 'pintu:emr-ri.rekam-medis.open'],   // rujukan: dari DALAM EMR RI
```

```php
#[On('emr-rj.administrasi.open')]
#[Authorize('pintu.emr-rj.administrasi.open')]   // Gate terdaftar otomatis dari katalog
public function openAdministrasiPasien(…)
```

- **Menambah pembuka baru** ke modal yang sudah punya pintu → tambahkan hak pembukanya ke
  entri pintunya (kalau lupa: mode `log` mencatat "Pintu modal di luar hak", mode `tolak` 403).
- **Modal baru** yang pembukanya menuntut hak lebih dari Lihat halaman → entri `'pintu'` di blok
  programnya + `#[Authorize('pintu.…')]` di listener. Modal yang hanya dibuka dari halamannya
  sendiri tak perlu pintu: persistent middleware sudah menjaga dengan hak Lihat halaman itu.
- Pemicu dengan nama event dinamis (`$this->dispatch($event)`) tidak terdeteksi pemindai —
  periksa manual (contoh: Dokumen Bedah di Kamar Operasi → `penunjang.kamar-operasi.bukaDokumenBedah`).

## Aturan khusus berbasis DATA tetap di kode

Contoh CPPT/SBAR RI: edit hanya **pemilik entri**, review hanya **DPJP Utama**. Itu bukan
role, jadi bukan centang. Yang jadi centang hanya **pengecualiannya**:

```php
$canEdit = (auth()->user()->can('ri.cppt.ubahSemua') || $isOwner) && !$isFormLocked;
```
