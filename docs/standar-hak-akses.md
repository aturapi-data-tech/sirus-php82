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
3. Guard di komponen (lihat pola di bawah).
4. Deploy, lalu **`php artisan akses:sinkron`** (lokal & produksi). Membuat 4 permission
   halaman baru + menyalin centang awal. Aman diulang: centang yang dicabut admin tidak kembali.
5. Admin menyesuaikan centang di Role Control.

Mengoreksi centang awal route yang permission-nya **sudah** ada:
`php artisan akses:sinkron --ulang=rj.daftar,ugd.daftar` — mengisi ulang centang route itu
dari definisi (menimpa Role Control untuk route itu saja; coba `--dry-run` dulu).

Halaman yang **tidak** tampil di menu (dibuka dari halaman hub) → jangan masuk AppMenu;
daftarkan induknya di `AksesHalaman::INDUK`. Lupa = halaman tertutup (di mode `log`: tercatat
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

## Aturan khusus berbasis DATA tetap di kode

Contoh CPPT/SBAR RI: edit hanya **pemilik entri**, review hanya **DPJP Utama**. Itu bukan
role, jadi bukan centang. Yang jadi centang hanya **pengecualiannya**:

```php
$canEdit = (auth()->user()->can('ri.cppt.ubahSemua') || $isOwner) && !$isFormLocked;
```

## Menambah aksi khusus

1. Konstanta role awal di `AksiRole` + entri di `AksiRole::KHUSUS` (area, label, roles).
2. Gate terdaftar otomatis (loop di `AppServiceProvider`) dan muncul di kolom Khusus Role Control.
3. `@can('nama')` di blade + `#[Authorize('nama')]` / `->can()` di server.
4. `php artisan akses:sinkron`.

Pengecualian: `AksiRole::EMR_PENUNJANG_LIHAT` tetap berbasis role (penanda profesi, bukan hak).
