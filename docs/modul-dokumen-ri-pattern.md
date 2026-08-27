# Pola Modul-Dokumen RI (formulir bertanda tangan, multi-entri)

Formulir EMR Rawat Inap yang **ditandatangani** dan bisa berulang: General Consent,
Inform Consent, Permintaan Kerohanian, Edukasi Terintegrasi, Penundaan Pelayanan,
Pengkajian Akhir Hayat, dst. Semua tampil sebagai tab di
`transaksi/ri/emr-ri/modul-dokumen/⚡modul-dokumen-ri.blade.php`.

Acuan kanonik: **Inform Consent RI** (paling lengkap) dan **Pengkajian Akhir Hayat**
(paling baru; contoh gabungan formulir KARS + RM.RI).

> Beda dengan `docs/emr-multi-entry-document-pattern.md` (CPPT/SBAR): di sana entri
> ditulis banyak PPA per-profesi & di-review DPJP. Di sini entri **ditandatangani
> pasien/keluarga + saksi + petugas**, lalu **terkunci** dan bisa dicetak.

---

## 1. Struktur file (3 titik sentuh)

```
resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/<nama>/rm-<nama>-actions.blade.php   ← komponen Volt
resources/views/pages/components/modul-dokumen/ri/<nama>/cetak-<nama>-print.blade.php       ← cetak PDF
resources/views/pages/transaksi/ri/emr-ri/modul-dokumen/⚡modul-dokumen-ri.blade.php           ← daftarkan tab
```

Pendaftaran tab = 2 tempat di file yang sama: `<x-tab …>` + panel `<div x-show="activeTab === '<key>'">`
berisi `<livewire:… :riHdrNo="$riHdrNo" :disabled="$isFormLocked" wire:key="…" />`.

## 2. Kerangka komponen

- Traits: `EmrRITrait, MasterPasienTrait, WithRenderVersioningTrait, WithValidationToastTrait`.
- Kartu ringkas + tombol → `openModal()` → `x-modal size=full height=full`.
- Data disimpan di **`datadaftarri_json`** dengan key khusus modul (mis. `pengkajianAkhirHayatRI`),
  berbentuk LIST entri: `['id', 'created_at', 'created_by', 'form' => [...], 'finalized' => bool]`.
- Tulis SELALU lewat `DB::transaction` + `lockRIRow()` + `updateJsonRI()` + `appendAdminLogRI(..., 'MR')`.
- `array_replace_recursive(defaultForm(), $entri['form'])` saat memuat entri lama —
  record lama yang belum punya key baru tetap aman (lihat feedback normalisasi JSON legacy).

## 2b. Dua layar: daftar dulu, formulir menyusul (BAKU sejak 2026-08-27)

Modal modul dokumen punya **dua layar**, tidak boleh menampilkan formulir dan daftarnya
sekaligus:

| Layar | Isi | Tombol |
|---|---|---|
| `daftar` (dibuka duluan) | grid entri tersimpan; per baris Edit/Lihat/Cetak/Hapus | `Tutup`, `Tambah Entri` |
| `form` | formulir entri | tombol simpan/TTD lama + `Kembali ke Daftar` (menggantikan `Tutup`) |

**Kenapa.** Dulu formulir nongkrong bersama daftarnya, lalu dikosongkan diam-diam sesudah
tersimpan. Petugas yang mengira itu masih formulir yang tadi diisi mengetik ulang di atasnya
— dan tersimpan sebagai **draft baru**. Duplikat draft ini keluhan nyata dari pemakaian.

### Potongan baku

```php
// Layar aktif di modal: 'daftar' (grid entri) atau 'form' (tambah/edit/lihat).
public string $layar = 'daftar';

/** Layar formulir sedang tampil? Saat terkunci, formulir tak pernah dirender. */
public function diForm(): bool
{
    return !$this->isFormLocked && ($this->viewOnly || $this->editingKey !== null || $this->layar === 'form');
}

public function tambahEntri(): void
{
    if ($this->isFormLocked || $this->disabled) { /* toast + return */ }
    $this->cancelEdit();     // kosongkan formulir (sekaligus balik ke daftar)…
    $this->layar = 'form';   // …lalu naikkan formulirnya
}

public function kembaliKeDaftar(): void { $this->cancelEdit(); }
```

**Kunci polanya ada di reset:** method `reset*()` yang mengosongkan formulir ikut menyetel
`$this->layar = 'daftar'`. Dengan begitu SETIAP jalur yang mengosongkan formulir — Simpan
Draft, finalize/TTD, batal edit, hapus entri yang sedang dibuka — otomatis kembali ke daftar
tanpa perlu diingat satu per satu. `openModal()` juga menyetelnya ke `'daftar'`.

Di markup: formulir dibungkus `@if ($this->diForm())`, daftar dibungkus
`@unless ($this->diForm())`, dan isi footer bercabang mengikuti keduanya.

### Susunan atas modal (BAKU)

Urutannya **display pasien dulu, baru judul** — mengikuti pola EMR, supaya identitas pasien
yang paling sering dicek petugas tidak tertutup blok judul besar:

```blade
{{-- DISPLAY PASIEN — paling atas, mengikuti pola EMR --}}
<div class="px-4 pt-4">
    <livewire:pages::transaksi.<jalur>.display-pasien-<jalur>.display-pasien-<jalur> … />
</div>

{{-- JUDUL RINGKAS --}}
<div class="relative px-6 py-3 border-b …">   {{-- py-3, bukan py-5 --}}
    … ikon w-8 h-8 · <h2> text-base (bukan text-2xl) · subjudul text-xs · badge · tombol tutup
</div>
```

Judulnya sengaja kecil: modal ini sudah dibuka dari tab yang bernama sama, jadi judul besar
cuma memakan ruang.

**Judul dan tombol tutup SEBARIS di paling atas** — judul kiri, X kanan, display pasien
menyusul di bawahnya:

```blade
{{-- JUDUL + TOMBOL TUTUP SEBARIS — judul di kiri, X di kanan, paling atas modal --}}
<div class="relative px-6 py-2.5 border-b …">
    <div class="relative flex items-center gap-3 min-w-0">
        <div class="flex items-center flex-1 gap-3 min-w-0">
            [ikon w-7] <div class="flex items-baseline gap-2 min-w-0">
                          <h2 class="truncate shrink-0 text-sm …">Judul</h2>
                          <p class="truncate text-xs …">Subjudul</p>
                       </div>
            <div class="flex items-center gap-1.5 ml-auto shrink-0"> [badge…] </div>
        </div>
        <x-icon-button … class="ml-2 shrink-0"> X </x-icon-button>
    </div>
</div>
```

Aturan yang lahir dari percobaan, jangan diulang:
- **Jangan** membuat X mengambang (`absolute` di pembungkus `relative`) — ia menimpa kartu
  display pasien dan menutupi nomor antrian.
- **Jangan** menaruh X di baris terpisah sendiri — barisnya nyaris kosong dan boros ruang.
- **Jangan** menumpuk judul/subjudul/badge ke bawah; semuanya `truncate` + `min-w-0` supaya
  memendek, bukan turun baris.

### Blok daftar: tanpa judul sendiri, tabel selebar kartunya

Blok daftar **tidak** memasang judul lagi (`<h3>Daftar … Tersimpan</h3>`) — judul modal di
baris atas sudah menyebutkan dokumennya, jadi judul kedua cuma memakan tinggi. Kartunya juga
tanpa padding dalam supaya tabel memakai ruang penuh:

```blade
@unless ($this->diForm())
    <x-border-form padding="p-0">   {{-- tanpa title, tanpa padding --}}
        <div class="overflow-x-auto rounded-2xl">   {{-- sudut ikut kartunya --}}
            <table class="min-w-full text-sm">…</table>   {{-- TANPA border/rounded sendiri --}}
        </div>
    </x-border-form>
@endunless
```

Bingkai & sudut cukup dari kartunya. Tabel yang membawa `border … rounded-lg` sendiri bikin
garis ganda dengan radius yang beda (lg di dalam 2xl), dan sudut header sticky-nya menyembul
keluar kartu.

**Kepala tabel menyontek `daftar-rj`** (acuan halaman list):

```blade
<thead class="sticky top-0 z-10 bg-surface-card dark:bg-gray-800">
    <tr class="text-xs font-semibold tracking-wide text-left text-muted uppercase dark:text-gray-300">
        <th class="px-4 py-3 border-b bg-surface-card dark:bg-gray-800">Tindakan</th>
        …
```

Latar dipasang di `<th>` juga, bukan cuma di `<thead>` — itu syarat header sticky supaya
baris tabel tak menembus di belakangnya saat digulir.

> **Awas saat menyisir berkas:** jangan memakai kata "Tersimpan"/"Riwayat" sebagai penyaring
> `grep` untuk menemukan modul dokumen — sejak judul blok dibuang, 18 berkas tak lagi memuat
> kata itu dan daftar berkasnya menyusut diam-diam (55 → 37). Penanda yang stabil:
> `grep -rl 'this->diForm()' resources/views/pages/transaksi`.

### Kartu di tab: pratinjau entri

Kartu modul di tab (sebelum modal dibuka) menampilkan **ringkasan entri terbaru** supaya
petugas tak perlu membuka modal hanya untuk memastikan sudah terisi:

```blade
{{-- PRATINJAU ENTRI DI KARTU — ringkasan entri terbaru, tanpa perlu membuka modal --}}
@if (count($daftar ?? []) > 0)
    <div class="mt-3 overflow-x-auto rounded-2xl border border-hairline …">
        <table class="min-w-full text-sm">   {{-- kolom = kolom tabel modal, tanpa panah & Aksi --}}
    </div>
    @if (count($daftar) > 3)
        <p class="…">+{{ count($daftar) - 3 }} entri lain — buka untuk melihat semua.</p>
    @endif
@endif
```

Kolomnya sengaja **diklon dari tabel di modalnya sendiri** (buang kolom panah & kolom Aksi),
supaya istilah dan urutan kolomnya sama persis — bukan dikarang ulang per modul.

### Cara memverifikasi (jangan cuma lihat "tidak error")

`php -l` maupun `Blade::compileString` TIDAK menangkap kerusakan berkas Volt, dan halaman
yang tetap ter-render belum berarti layarnya berpindah. Ukur tiga hal lewat `Livewire::test`:

1. render `daftar` → `tambahEntri` → `kembaliKeDaftar` tidak melempar;
2. **isian formulir benar-benar hilang** di layar daftar — hitung `wire:model` pada HTML
   kedua layar (harus 0 vs puluhan). Ini yang menangkap "konversi palsu" (12 berkas sempat
   lolos render tapi tak menyembunyikan apa pun);
3. tombol ada di layar yang benar (`saveDraft` 0 di daftar, `tambahEntri` 0 di formulir);
4. **daftar TETAP menampilkan tabelnya saat kosong**, lengkap dengan baris
   `<td colspan="N">Belum ada data tersimpan</td>` (pola empty state `daftar-rj`). Jangan
   membungkus tabel dengan `@if (count($list) > 0)` — layar daftar jadi kosong melompong dan
   petugas tak tahu apakah datanya nihil atau halamannya rusak;
5. **keseimbangan tag pada HTML HASIL RENDER di kedua layar** (`<div>` vs `</div>`, section,
   fieldset, table, ul/ol, p, span).

Lapis 5 itu wajib dan tidak bisa digantikan pemeriksa statis. Menghitung tag pada BERKAS
tidak cukup: rentang yang menutup `<div>` header lalu membuka `<div>` isi terhitung
"seimbang" padahal sarangnya salah. Render komponen sendirian juga tidak cukup: anak yang
kelebihan satu `</div>` tetap terlihat wajar berdiri sendiri, dan baru meledak saat
bersarang di komponen induk — `MultipleRootElementsDetectedException` pada INDUK, bukan pada
berkas yang salah. Sepuluh berkas pernah lolos tiga lapis pertama dengan cacat ini.

Kalau modul yang dikonversi ikut dipasang di komponen payung (mis. Pelayanan Bedah yang
menampung 8 anak), buka juga halaman daftarnya (`/ri/daftar`, `/rj/daftar`, `/ugd/daftar`)
dan pastikan 200.

## 3. Siklus hidup entri (BAKU)

```
Simpan Draft (tanpa validasi)  →  pasien/keluarga TTD (+saksi, opsional)
   →  PETUGAS TTD  =  validasi penuh + stempel + KUNCI entri
   →  entri read-only: hanya Lihat / Cetak
   →  [Admin | Manager Umum | Manager Medis]  Buka Kunci  →  kembali draft, TTD petugas dicabut
```

Aturan yang mengikat:
- **TTD petugas adalah aksi terakhir dan sekaligus pengunci** (`setDokterPenjelas` di Inform
  Consent, `ttdPetugas()` di Akhir Hayat). JANGAN sediakan tombol "Simpan & Kunci" terpisah —
  dua jalan mengunci = perilaku bercabang.
- Varian **multi-TTD petugas** (Surgical Safety Checklist: Perawat Sirkuler + Dokter Anestesi +
  Operator, TANPA TTD pasien): satu aksi `setTtdRole($role)` dipakai semua peran (peta peran di
  konstanta `TTD_ROLES`), entri terkunci **otomatis saat TTD terakhir terisi** (`semuaTtdTerisi()`) —
  bukan lewat satu method pengunci khusus. Buka kunci pada varian ini mencabut **semua** TTD
  petugas (aturan "TTD pasien dipertahankan" tak berlaku karena memang tak ada TTD pasien).
- Tombol footer cukup **Simpan Draft** (jadi "Simpan Perubahan" saat melanjutkan draft).
- `entryIsFinal()` baca flag `finalized`; fallback record lama = ada TTD pasien/keluarga.
- Entri final tak boleh ditimpa: `persistEntry()` melempar RuntimeException bila targetnya final.
- **Buka kunci** hanya mencabut `finalized` + **TTD petugas**; TTD pasien/keluarga & saksi
  DIPERTAHANKAN (tak boleh dihapus sepihak oleh staf) + audit log wajib menyebut pelakunya.
  Gate dua lapis: `@can('dokumen.bukaKunci')` di tombol **dan** `bolehBukaKunci()` (yang mengembalikan
  `auth()->user()?->can('dokumen.bukaKunci')`) di server.
- **Hapus entri** juga digate: `@can('dokumen.hapus')` di tombol + guard
  `if (!auth()->user()?->can('dokumen.hapus')) { toast; return; }` sebagai statement pertama
  method hapus. Berlaku baik untuk draft maupun entri final.
- **Role terpusat**: daftar role Hapus & Buka Kunci ada di **satu file** `App\Support\AksiRole`
  (konstanta `DOKUMEN_HAPUS` & `DOKUMEN_BUKA_KUNCI`, saat ini triad `Admin | Manager Umum | Manager Medis`), didaftarkan
  sebagai Gate `dokumen.hapus` & `dokumen.bukaKunci` di `AppServiceProvider::boot()`. Menambah role =
  ubah 1 file itu. **JANGAN** tulis `@hasanyrole('Admin|...')`/`hasAnyRole([...])` literal di modul dokumen.

## 4. Tanda tangan

| Pihak | Cara | Wajib? |
|---|---|---|
| Pasien / keluarga | `x-signature.signature-pad` → `signature-result` bila sudah ada | wajib |
| Saksi | idem | opsional (`nullable`) — tampilkan langsung, jangan sembunyikan di balik tombol |
| Petugas | `x-signature.ttd-petugas` (`:framed=false`, `:allowClear=false`) | wajib; menstempel nama+kode+jam user login |

- TTD ikut `rules()` (`'signature' => 'required|string'`) supaya error tampil **merah di
  kolomnya** + toast — jangan cek manual yang cuma memunculkan toast.
- Nama/waktu petugas TIDAK divalidasi: di-stempel oleh aksinya sendiri.
- Tiga kolom TTD dibuat **sama tinggi** (`items-stretch` + `h-full flex flex-col`, area TTD `flex-1`).
- Cetak: gambar TTD pasien dari base64 di JSON; TTD petugas dari `users.myuser_ttd_image`
  via `petugasCode`. Layout TTD di PDF wajib `<table>` (lihat `docs/ttd-pattern-pdf-print.md`).

## 5. Validasi — seminimal mungkin

Formulir klinis diisi bertahap. Mewajibkan banyak field hanya membuat petugas mengetik asal
supaya bisa mengunci. Wajibkan hanya: **tanggal**, **nama + hubungan penanda tangan**, **TTD**.
Aturan bersyarat dipakai hanya bila tanpa isian itu datanya tak bermakna
(mis. pilih "Donasi organ" → organnya wajib disebut).

Tanggal SELALU `date_format:d/m/Y H:i:s` (standar repo, ±95 tempat).

### 5a. Kolom tanggal/jam — teks + `x-now-button`, JANGAN `type="date"`/`type="time"`

Input HTML native menyimpan `Y-m-d` / `H:i`, bentrok dengan format sisa JSON EMR
(`d/m/Y H:i:s`) sehingga cetak & display tampil beda gaya, dan validasi
`date_format:d/m/Y H:i:s` otomatis gagal. Pakai `<x-text-input>` teks biasa:

```blade
{{-- tanggal + jam --}}
<div class="flex gap-1 mt-1">
    <x-text-input wire:model="newForm.FIELD" placeholder="dd/mm/yyyy HH:mm:ss" class="w-full" />
    <x-now-button wire:click="setNow('FIELD')" :disabled="$formReadOnly" />
</div>

{{-- tanggal murni (HPHT, tgl lahir, dll) --}}
<div class="flex gap-1 mt-1">
    <x-text-input wire:model="newForm.FIELD" placeholder="dd/mm/yyyy" class="w-full" />
    <x-now-button wire:click="setToday('FIELD')" :disabled="$formReadOnly" title="Set ke tanggal hari ini" />
</div>
```

Method baku di komponen — nama `setNow`/`setToday` (samakan lintas modul, jangan
`setJamSekarang`/`setTglSekarang`/`setTglJamSekarang`):

```php
public function setNow(string $field): void   { /* guard */ $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y H:i:s'); }
public function setToday(string $field): void { /* guard */ $this->newForm[$field] = Carbon::now(config('app.timezone'))->format('d/m/Y'); }
```

Tombolnya di-gate lewat `:disabled="$formReadOnly"`, bukan dibungkus `@if (!$formReadOnly)`.
**Satu peristiwa = satu kolom**: jangan pasangan `xxxTgl` + `xxxJam` terpisah — gabung jadi
satu kolom datetime (`ketubanPecah`, `hisMulai`, …). Pasangan terpisah memaksa helper
`$tgljam($t,$j)` di tiap blade cetak dan gampang tidak sinkron.

### 5d. Data berulang di dalam satu entri = tabel entri, BUKAN entri dokumen terpisah

Kalau satu dokumen wajar berisi banyak baris sejenis (titik-waktu observasi, obat
pre-medikasi, riwayat kehamilan), simpan sebagai **`rows` di dalam SATU entri**, jangan
dijadikan banyak entri dokumen. Entri dokumen membawa siklus Draft→TTD→Kunci sendiri;
memakainya untuk tiap baris memaksa petugas menandatangani berulang padahal cetaknya
memang satu lembar.

Acuan: **Obat Pre Medikasi** di `pra-induksi-ri`. Bentuk bakunya:

- Property input baris di atas tabel: `public string $barisXxx = ''` per kolom.
- `barisKosong()` — bentuk satu baris; `normalizeRows(array $entry)` — `array_replace`
  tiap baris dengan `barisKosong()` supaya baris lama yang kolomnya belum lengkap aman,
  sekaligus membaca entri LEGACY yang masih datar (tanpa `rows`) sebagai satu baris.
- `tambahBaris()` — guard read-only → `validateWithToast()` (kolom kunci `required`,
  sisanya `nullable`) → push → `resetBarisInput()`. `hapusBaris(int $index)` → `unset` +
  `array_values`.
- Kolom waktu di-sort lewat `Carbon::createFromFormat('d/m/Y H:i:s', …)`, JANGAN
  leksikografis — `01/08` akan mendahului `02/07`.
- Markup: blok field (`@if (!$formReadOnly)`) → tombol **Tambah** full-width → tabel
  **baca-saja** `ds-table` `No | …kolom… | Aksi` dengan `x-confirm-button
  variant="danger-soft"` dan baris kosong `Belum ada …`. Tabel selalu tampil; saat
  read-only kolom Aksi jadi `—`.
- Cetak & viewer ikut per-entri: `cetak($createdAt)` mengambil `rows` dari entri itu,
  sedangkan `diagnosa`/`ttd` diambil dari ENTRI (bukan di-pluck dari baris).

### 5c. Penamaan variabel di blade cetak — nama LENGKAP

Blade cetak dulu memakai singkatan satu huruf (`$v`, `$c`, `$r`, `$k`, `$i`) sehingga sulit
diaudit programmer lain. Paling parah: `$id` di sana berarti **identitas pasien**, bukan
identifier, dan helper yang isinya identik dipakai dengan tiga nama berbeda
(`$v` / `$cell` / `$show`). Peta baku sekarang:

| Peran | Nama |
|---|---|
| `$data['identitas']` | `$identitas` (JANGAN `$id`) |
| helper ambil nilai dari `$form` by key | `$nilaiForm = fn(string $field) => …` |
| helper format satu nilai/sel tabel | `$nilaiSel = fn($nilai) => …` |
| helper ambil nilai dari satu baris | `$nilaiBaris = fn(array $baris, string $field) => …` |
| helper implode array jadi teks | `$nilaiFormList` |
| item loop baris | `$baris` |
| indeks loop | `$nomor` |
| parameter closure berisi nama field | `$field` (`$fieldTgl`/`$fieldJam` bila spesifik) |

Boleh tetap: `$data`, `$form`, `$row`/`$rows` (idiom repo), `$loop`. Beri type hint pada
parameter closure supaya niatnya terbaca tanpa menelusuri pemanggil.

**Verifikasi rename blade WAJIB render nyata** — `php -l` dan `view:cache` TIDAK menangkap
variabel yang putus. Render versi lama (`git show HEAD:<path>`) dan versi baru dengan payload
dummy yang sama, lalu bandingkan md5-nya; harus identik.

### 5b. Lebar isi modal = `max-w-full`

Body `<x-modal size="full">` diisi `<div class="max-w-full mx-auto space-y-4">`. Memakai
`max-w-5xl` menyisakan ruang kosong kiri-kanan yang mencolok pada formulir bergrid banyak
kolom (dikeluhkan user 2026-08-11 di Pengkajian Awal Obstetri).

## 6. Teks legal → clause-versioning

Kalimat pernyataan/persetujuan TIDAK ditulis inline di blade. Taruh di
`App\Support\<Nama>Clause`, stempel `clauseVersion` per entri, cetak pakai versi tersimpan.
Lihat `docs/clause-versioning.md` + skill `clause-versioning`.

## 7. Rancangan isi form (pelajaran dari Akhir Hayat)

- Pecah jadi **panel bernomor sama dengan formulir kertas** (`x-border-form :collapsible`),
  hanya panel awal `:open="true"`. Satu panel raksasa = melelahkan.
- Sub-kelompok di dalam panel diberi **bingkai** + judul kecil uppercase, dipasang kanan-kiri.
  Untuk pasien vs keluarga: dua kartu sejajar, isi & urutan field dibuat cermin.
- Skala keparahan (Tidak ada/Ringan/Sedang/Berat) lebih ringkas daripada belasan checkbox
  gejala — beri **warna gradasi** (hijau→kuning→oranye→merah) dan klik ulang = batal pilih.
- Gabungkan opsi bersinonim, TAPI **jangan gabungkan diagnosis keperawatan (SDKI) atau
  tindakan medis** — tiap butir keputusan klinis harus berdiri sendiri.
- Cek tumpang tindih antar bagian sebelum rilis (mis. "rencana rawat di rumah" vs "instruksi
  perawatan di rumah"; "dukungan/kunjungan" vs "intervensi keperawatan").
- Isi otomatis dari data yang sudah ada (diagnosis & TTV dari Pengkajian Awal), tetap bisa dikoreksi.

## 8. Jebakan Blade yang SUDAH pernah menggigit di modul ini

1. **Escape ganda pada prop yang di-echo ulang komponen.**
   `value="A &amp; B"` / `title=` / `nameLabel=` / `signLabel=` → komponen meng-echo `{{ }}`
   lagi → layar menampilkan `&amp;`. Tulis `&` polos di atribut komponen; `&amp;` hanya untuk
   teks HTML biasa. Untuk nilai dinamis pakai **`:value="$x"`**, bukan `value="{{ $x }}"`.
2. **Tag `x-...` di dalam komentar PHP tetap dikompilasi** → ParseError/`Undefined $component`.
   Sebut nama komponennya tanpa angle-bracket.
3. `x-radio-button` props aslinya `label/value/name` + `wire:model.live` (tidak ada
   `current`/`wireClick`); `signature-pad` hanya punya `wireMethod`. Jangan mengarang prop.
4. Verifikasi sebelum lapor: compile via `Blade::compileString` + `php -l`, hitung
   keseimbangan `<div>`/`<fieldset>`/`<x-border-form>`, dan render dengan data contoh
   lalu cek `Unexpected end tag` via DOMDocument.

> Catatan `Blade::compileString`: sebagian file host (mis. `cetak-rekam-medis-open`)
> **tidak** standalone-compilable (pakai `@if` yang terentang via `@include`/slot) —
> hasilnya "unexpected endif". Bandingkan dgn versi `git HEAD`: kalau HEAD gagal identik,
> itu pre-existing, bukan salahmu. Validasi final yang sahih = **`php artisan view:cache`**
> (EXIT 0 = semua view kompilasi lewat pipeline Blade asli), lalu `view:clear`.

---

## 9. Port ke jalur lain (RI ⇄ UGD ⇄ RJ)

Satu form dokumen sering harus tersedia di lebih dari satu jalur. **Jangan tulis ulang** —
salin file actions + cetak dari jalur acuan, lalu ganti token berikut (per-string, bukan
`RI→UGD` global karena banyak identifier mengandung "RI"):

| RI | UGD | RJ |
|----|-----|----|
| `Txn\Ri\EmrRITrait` | `Txn\Ugd\EmrUGDTrait` | `Txn\Rj\EmrRJTrait` |
| `?string $riHdrNo` | `?int $rjNo` | `?int $rjNo` |
| `$dataDaftarRi` | `$dataDaftarUGD` | `$dataDaftarPoliRJ` |
| `findDataRI` / `checkEmrRIStatus` | `findDataUGD` / `checkEmrUGDStatus` | `findDataRJ` / … |
| `updateJsonRI` / `appendAdminLogRI` / `lockRIRow` | `…UGD` | `…RJ` |
| key JSON `pengkajian<Dok>RI` | `pengkajian<Dok>UGD` | `…RJ` |
| modal `rm-<dok>-ri-` · area `modal-<dok>-ri` | `…-ugd` | `…-rj` |
| `pages::transaksi.ri.display-pasien-ri.display-pasien-ri :riHdrNo` | `pages::transaksi.ugd.display-pasien-ugd.display-pasien-ugd :rjNo` | `pages::transaksi.rj.display-pasien-rj.display-pasien-rj :rjNo` |
| prefill `pengkajianAwalPasienRawatInap…` | path EMR UGD (mis. `diagnosisFreeText`) | path EMR RJ |
| loadView `…r-i.<dok>.cetak-<dok>-ri-print` | `…u-g-d.<dok>.cetak-<dok>-print` | `…r-j.…` |

> ⚠ Ganti tag `display-pasien` **utuh termasuk prefix namespace** `pages::transaksi.<jalur>.` —
> jebakan nyata: replace per-token hanya mengganti sufiks komponen sehingga tersisa
> `pages::transaksi.ri.display-pasien-rj.…` (komponen salah jalur, tapi Blade tetap kompilasi).
> Sapu jaring pengaman: `grep -rn "pages::transaksi\.ri\." resources/views/pages/transaksi/{rj,ugd}/`.

Konvensi nama: folder/file **UGD/RJ membuang sufiks** `-ri` (`…/akhir-hayat/rm-akhir-hayat-actions`),
tapi **modal-name / renderArea / nama file PDF tetap** memakai `-ugd`/`-rj`. `regNo`/`regName`
tersedia di data UGD/RJ juga, jadi cetak inline + `MasterPasienTrait` tetap jalan.
`App\Support\*Clause` & `App\Support\*Options` (peta label cetak) **dipakai bersama** semua jalur —
jangan diduplikasi. Verifikasi: `view:cache` EXIT 0 + grep tidak ada token RI nyasar.

## 10. Viewer di display Rekam Medis (WAJIB saat menambah dokumen)

Menambah form baru **belum selesai** sampai dokumennya bisa dilihat di **display Rekam Medis**.
Pola viewer read-only (Lihat = render blade cetak ke iframe) ada di
`docs/dokumen-view-pattern.md`. Untuk tiap jalur yang dipasang:

1. Buat komponen viewer `resources/views/pages/components/rekam-medis/<jalur>/dokumen-view/<dok>-view-<jalur>.blade.php`.
2. **Daftarkan** di `…/rekam-medis/<jalur>/cetak-rekam-medis/cetak-rekam-medis-open.blade.php`
   (RI pakai var `$ri` + `:riHdrNo`; UGD pakai `$txn` + `:rjNo`), oper `:entries="$rec['pengkajian<Dok><Jalur>'] ?? []"`.
3. Dokumen dgn cetak **payload seragam** (dataRi/form/ttd) → pakai
   `DokumenViewSupportTrait::previewDokumenRi()/streamCetakDokumenRi()` langsung.
4. Dokumen dgn cetak **payload bespoke** (butuh `entry` + `opsiLabel` + `clause`, mis. Akhir Hayat)
   → viewer **self-contained**: pakai `dvPasien/dvTtdPath/dvIdentitasRs/renderDokumenPreview`
   + `buildData()` yang meniru `cetak()` komponen EMR. Taruh peta label di
   `App\Support\<Dok>Options::labels()` supaya satu sumber untuk semua jalur.

## 11. Penanda tab (badge "ada data")

Tiap `<x-tab>` di hub `modul-dokumen-<jalur>.blade.php` **wajib** menampilkan badge saat
dokumennya sudah ada isi — supaya user tahu tab mana yang berisi tanpa membukanya. Hub sudah
memegang seluruh JSON EMR di `$dataDaftarRi` / `$dataDaftarUGD` / `$dataDaftarPoliRJ`, jadi badge
membaca langsung dari `$dataDaftar<Jalur>['<jsonKey>']`. Sisipkan **sebelum `</x-tab>`**, gaya
seragam `<x-badge variant="success" class="text-[10px] px-1.5 py-0">…</x-badge>`.

| Tipe dokumen | Guard | Isi badge |
|---|---|---|
| **multi** (array entri) | `@if (count($key ?? []) > 0)` | `{{ count($key) }}` (angka) |
| **single** (satu objek) | `@if (!empty($key['signature']))` / `['isFinal']` | `&#10003;` (atau `TTD`) |
| **dual** (mis. `formMPP`) | `@if ($n > 0)` dgn `$n = count(formA)+count(formB)` | `{{ $n }}` |
| **umbrella** (tab berisi banyak sub-dok, mis. Pelayanan Bedah / VK) | `@if (collect([...childKeys])->first(fn($k) => !empty($dataDaftar<Jalur>[$k])))` | `&#10003;` |

Contoh (multi): `@if (count($dataDaftarRi['informConsentPasienRI'] ?? []) > 0) <x-badge …>{{ count($dataDaftarRi['informConsentPasienRI']) }}</x-badge> @endif`.
Contoh (umbrella): daftar semua child key dokumen di dalam tab (Pelayanan Bedah = 8 dok bedah/anestesi;
VK/Kebidanan = 11 dok obstetri/neonatal) lalu `->first(...)` → `&#10003;` bila salah satu ada data.
`suket` (RJ/UGD) = container dua sub-key single → `!empty($key['suketSehat']) || !empty($key['suketIstirahat'])`.
