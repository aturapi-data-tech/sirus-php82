# Standar Hak Akses (Role → Permission → Menu/Aksi)

## Konsep

```
User ──(model_has_roles)──> Role ──(role_has_permissions)──> Permission
```

- **Menu terhubung ke ROLE, bukan ke user.** User mendapat gabungan hak dari semua role-nya.
- Centang diatur per role di **Role Control** (`/database-monitor/role-control`), bentuk matriks.
- Dua jenis permission:

| Jenis | Nama | Contoh | Sumber katalog |
|---|---|---|---|
| Halaman | `{nama-route}.{lihat\|tambah\|ubah\|hapus}` | `master.poli.hapus` | `App\Services\AppMenu` (setiap menu) |
| Aksi khusus | nama Gate bebas | `dokumen.bukaKunci`, `ri.cppt.ubahSemua` | `App\Support\AksiRole::KHUSUS` |

- **Lihat** = menu tampil **dan** URL boleh dibuka. Dijaga middleware `akses.halaman`
  (`App\Http\Middleware\CekAksesHalaman`), juga untuk setiap aksi Livewire (persistent middleware).
- **Tambah/Ubah/Hapus/Khusus** = dijaga di komponen (blade + server).
- Aturan pusat: `App\Support\AksesHalaman`. Deny-by-default: route yang tak dikenal ditolak.
- Mode penjaga URL: `AKSES_HALAMAN_MODE=log` (izinkan + catat ke `storage/logs/akses-halaman-*.log`)
  atau `tolak` (403). Role Control & User Control selalu 403 di kedua mode.

## Menambah menu / halaman baru

1. Route di dalam grup `Route::middleware(['auth', 'akses.halaman'])` di `routes/web.php`.
2. Entri di `AppMenu::definitions()` — kolom `roles` = **centang awal** saja. Opsional:
   - `'aksi' => ['lihat', 'tambah', 'ubah']` — aksi yang berlaku; sisanya tampil `·` di Role
     Control dan tidak dibuat permission-nya (mis. halaman tanpa Hapus).
   - `'rolesAksi' => ['tambah' => [...], 'ubah' => [...]]` — centang awal per aksi bila beda
     dari `roles` (contoh: Daftar RJ/UGD/RI — banyak role boleh Lihat, hanya
     `AppMenu::ROLES_PENDAFTARAN` yang boleh Tambah/Ubah).
   - `'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', [roles]]]` — tombol khusus
     halaman (pembuka modul lain, dll.). Permission `{route}.{kunci}`, tampil di kolom
     **Khusus** baris halaman itu di Role Control. Pakai `@can('rj.pelayanan.bukaAdministrasi')`
     + `#[Authorize(...)]` di method pembukanya.
3. Guard di komponen (lihat pola di bawah).
4. Deploy, lalu **`php artisan akses:sinkron`** (lokal & produksi). Membuat 4 permission
   halaman baru + menyalin centang awal. Aman diulang: centang yang dicabut admin tidak kembali.
5. Admin menyesuaikan centang di Role Control.

Mengoreksi centang awal route yang permission-nya **sudah** ada:
`php artisan akses:sinkron --ulang=rj.daftar,ugd.daftar` — mengisi ulang centang route itu
dari definisi (menimpa Role Control untuk route itu saja; coba `--dry-run` dulu).

**Aturan seragam kolom aksi (2026-11-25)** — tulis `'aksi'` EKSPLISIT di tiap entri, jangan
mengandalkan default empat kolom (centang yang tak menjaga apa pun menyesatkan admin):

| Tombol di halaman | Masuk ke |
|---|---|
| Membuat / mengubah / menghapus data **di halaman itu sendiri** | kolom Tambah / Ubah / Hapus |
| Membuka modul lain (EMR, Administrasi, Modul Dokumen, Telaah) | `'khusus'` → `buka*` |
| Kirim ke pihak luar (Aplicares/SIRS, DINKESTA, …) | `'khusus'` → `kirim` |
| Posting / batal transaksi, aksi dari modal lintas halaman | Aksi Khusus `AksiRole::KHUSUS` |
| Identitas profesi (TTD dokter, telaah apoteker) | tetap di kode, bukan centang |

Halaman daftar/antrian yang hanya membuka modul = `'aksi' => ['lihat']` + `'khusus'`. Modal yang
dipakai banyak halaman (Berkas BPJS, Administrasi) dijaga **pintu modal**, bukan kolom halaman.
Modal Tambah/Edit yang juga dipakai untuk melihat/mencetak: jaga `openCreate` + `save()` (baru =
tambah, lama = ubah), JANGAN jaga `openEdit` (contoh: Pelaporan Down Time, penerimaan gudang).

Laporan manajemen = **menu sendiri** di AppMenu (grup Laporan Pelayanan/Penunjang/SIRS
Online/Keuangan, `'aksi' => ['lihat']`); halaman hub berisi kartu tautan sudah dihapus
(2026-11-25) — jangan membuat hub tautan baru (menu kembar dengan sidebar).

`AksesHalaman::INDUK` hanya untuk halaman turunan yang memang tak layak jadi menu (mis. Tutup
Buku Supplier dari Tutup Buku). Lupa didaftarkan = halaman tertutup (di mode `log`: tercatat
`"dikenal": false`).

Sebelum `akses:sinkron` dijalankan, semua cek memakai cadangan `roles` AppMenu / konstanta
AksiRole — deploy tanpa sinkron tidak mengunci siapa pun.

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
console browser. Listener modal dijaga dengan **pintu**: katalog `AksesHalaman::PINTU`
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
  entri katalog (kalau lupa: mode `log` mencatat "Pintu modal di luar hak", mode `tolak` 403).
- **Modal baru** yang pembukanya menuntut hak lebih dari Lihat halaman → tambah entri katalog +
  `#[Authorize('pintu.…')]` di listener. Modal yang hanya dibuka dari halamannya sendiri tak perlu
  pintu: persistent middleware sudah menjaga dengan hak Lihat halaman itu.
- Pemicu dengan nama event dinamis (`$this->dispatch($event)`) tidak terdeteksi pemindai —
  periksa manual (contoh: Dokumen Bedah di Kamar Operasi → `penunjang.kamar-operasi.bukaDokumenBedah`).

## Aturan khusus berbasis DATA tetap di kode

Contoh CPPT/SBAR RI: edit hanya **pemilik entri**, review hanya **DPJP Utama**. Itu bukan
role, jadi bukan centang. Yang jadi centang hanya **pengecualiannya**:

```php
$canEdit = (auth()->user()->can('ri.cppt.ubahSemua') || $isOwner) && !$isFormLocked;
```

## Tombol di komponen yang dipakai lintas halaman

Tombol di dalam EMR (`emr-rj-isi`, `emr-ri-isi`, …) tampil di beberapa halaman, jadi
tidak bisa memakai `{route}.{kunci}`. Pakai aksi khusus jalur di `AksiRole::KHUSUS`:
`rj.emr.bukaAdministrasi`, `ri.emr.bukaEresep`, dst. (area "EMR RJ/UGD/RI").

## Menambah aksi khusus

1. Konstanta role awal di `AksiRole` + entri di `AksiRole::KHUSUS` (area, label, roles).
2. Gate terdaftar otomatis (loop di `AppServiceProvider`) dan muncul di kolom Khusus Role Control.
3. `@can('nama')` di blade + `#[Authorize('nama')]` / `->can()` di server.
4. `php artisan akses:sinkron`.

Pengecualian: `AksiRole::EMR_PENUNJANG_LIHAT` tetap berbasis role (penanda profesi, bukan hak).
