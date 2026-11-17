# Standar Komponen Tombol

> Bagian dari [Standar UI & Komponen](standar-ui-komponen.md) — lihat juga standar modal, form section, tabel, dan input number.

Panduan penggunaan komponen tombol (`resources/views/components/*-button.blade.php`) agar konsisten di seluruh aplikasi.

---

## Daftar Komponen

### 1. `<x-primary-button>` — Aksi Utama

Solid hijau (brand-green). Hanya untuk **satu aksi utama** per modal/form.

```blade
<x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
    wire:loading.attr="disabled" :disabled="$isFormLocked">
    <span wire:loading.remove>Simpan</span>
    <span wire:loading><x-loading /> Menyimpan...</span>
</x-primary-button>
```

**Kapan pakai:** Simpan, Submit, Tambah (aksi utama)
**Jangan pakai untuk:** Navigasi, link ke halaman lain

---

### 2. `<x-secondary-button>` — Aksi Sekunder

Abu-abu muda (`bg-gray-100`) + border abu (`border-gray-300`).

```blade
<x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
```

**Kapan pakai:** Batal, Reset, Close (di footer modal)
**Jangan pakai untuk:** Close X di header (pakai `icon-button`), aksi hapus

---

### 3. `<x-outline-button>` — Aksi Alternatif / Tab

Tint hijau (`bg-brand-green/10`) + border hijau (`border-brand-green/30`). Hover berubah solid hijau.

```blade
<x-outline-button wire:click="switchTab('gizi')">Gizi</x-outline-button>
<x-outline-button wire:click="switchTab('nyeri')">Nyeri</x-outline-button>
```

**Kapan pakai:** Tab navigasi, pilihan alternatif, toggle view
**Jangan pakai untuk:** Aksi utama (pakai `primary-button`)

---

### 4. `<x-ghost-button>` — Navigasi / Link

Tint hijau sangat tipis (`bg-brand-green/5`) + border tipis (`border-brand-green/20`). Paling ringan secara visual.

```blade
<a href="{{ route('master.pasien') }}" wire:navigate>
    <x-ghost-button type="button">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        Master Pasien
    </x-ghost-button>
</a>
```

**Kapan pakai:** Link ke halaman lain (Master Pasien), navigasi sekunder, riwayat
**Jangan pakai untuk:** Aksi yang mengubah data

---

### 5. `<x-icon-button>` — Tombol Ikon

Kotak kecil (`p-2`) transparan + border. Mendukung warna: `green`, `blue`, `red`, `yellow`, `gray`.

```blade
{{-- Close X di header modal --}}
<x-icon-button color="gray" type="button" wire:click="closeModal">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd"
            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
            clip-rule="evenodd" />
    </svg>
</x-icon-button>

{{-- Cetak SEP --}}
<x-icon-button color="blue" type="button" wire:click="cetakSEP" title="Cetak SEP">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
    </svg>
</x-icon-button>
```

**Referensi warna:**

| Color | Kegunaan |
|-------|----------|
| `gray` | Close X, aksi netral |
| `blue` | Cetak, info |
| `green` | Aksi positif |
| `red` | Hapus (ikon kecil di dalam form) |
| `yellow` | Peringatan |

**Kapan pakai:** Tombol yang hanya berisi ikon (Close X, Cetak, Edit, Hapus item kecil)
**Jangan pakai untuk:** Tombol dengan teks (pakai komponen lain)

---

### 6. `<x-info-button>` — Aksi BPJS / SEP

Solid biru (`bg-blue-600`).

```blade
<x-info-button type="button" wire:click="openVclaimModal" class="gap-2 text-xs">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
    Kelola SEP BPJS
</x-info-button>
```

**Kapan pakai:** Kelola SEP, SPRI, Cetak SEP (dengan teks), aksi terkait BPJS/VClaim
**Jangan pakai untuk:** Aksi umum yang tidak terkait BPJS

---

### 7. `<x-success-button>` — Aksi Positif / Selesai

Solid lime (`bg-brand-lime`), teks gelap.

```blade
<x-success-button wire:click="serah">Serah Obat</x-success-button>
```

**Kapan pakai:** Serah obat (apotek), konfirmasi selesai, aksi final positif

---

### 8. `<x-warning-button>` — Aksi Peringatan

Solid kuning (`bg-yellow-500`).

```blade
<x-warning-button wire:click="forceUpdate">Update Paksa</x-warning-button>
```

**Kapan pakai:** Aksi yang perlu perhatian ekstra tapi bukan destruktif

---

### 9. `<x-danger-button>` — Aksi Destruktif

Solid merah (`bg-red-600`). **Disarankan pakai `confirm-button` sebagai gantinya** untuk aksi hapus data penting.

```blade
<x-danger-button wire:click="hapus">Hapus</x-danger-button>
```

**Kapan pakai:** Aksi destruktif ringan (hapus item dalam form yang belum disimpan)
**Untuk hapus data penting:** Pakai `<x-confirm-button variant="danger">` (lihat di bawah)

---

### 10. `<x-confirm-button>` — Aksi dengan Konfirmasi

Tombol yang memunculkan dialog konfirmasi styled sebelum eksekusi. Mendukung variant: `danger`, `primary`, `secondary`, `outline`.

```blade
{{-- Hapus data penting --}}
<x-confirm-button variant="danger" :action="'requestDelete(\'' . $id . '\')'"
    title="Hapus Data" message="Yakin ingin menghapus? Tindakan ini tidak dapat dibatalkan."
    confirmText="Ya, hapus" cancelText="Batal">
    Hapus
</x-confirm-button>

{{-- Batal transaksi --}}
<x-confirm-button variant="danger" action="batalTransaksi()"
    title="Batal Transaksi"
    message="Yakin ingin membatalkan transaksi? Semua data pembayaran akan dihapus."
    confirmText="Ya, batalkan" cancelText="Batal">
    Batal Transaksi
</x-confirm-button>

{{-- Transfer dengan peringatan --}}
<x-confirm-button variant="warning" action="transferKeUGD()"
    title="Transfer ke UGD"
    message="Yakin ingin mentransfer biaya RJ ini ke UGD?"
    confirmText="Ya, transfer" cancelText="Batal">
    Transfer ke UGD
</x-confirm-button>
```

**Props:**

| Prop | Default | Keterangan |
|------|---------|------------|
| `variant` | `danger` | `danger` / `primary` / `secondary` / `outline` |
| `action` | (wajib) | Method Livewire yang dieksekusi, contoh: `"delete('10')"` |
| `title` | `Konfirmasi` | Judul dialog |
| `message` | `Apakah Anda yakin?` | Pesan dialog |
| `confirmText` | `Ya` | Teks tombol konfirmasi |
| `cancelText` | `Batal` | Teks tombol batal |
| `disabled` | `false` | Nonaktifkan tombol |

**WAJIB pakai untuk:**
- Hapus data dari database (master, transaksi)
- Hapus SEP dari server BPJS
- Batal transaksi (kasir)
- Hapus dokumen bertanda tangan (inform consent, form penjaminan)

**Jangan pakai `wire:confirm`** (browser native) — selalu pakai `<x-confirm-button>` agar dialog styled dan konsisten.

---

### 11. `<x-radio-button>` — Input Radio Bergaya Tombol

Bukan tombol aksi, melainkan input radio yang ditampilkan sebagai tombol.

```blade
<x-radio-button :label="$klaim['klaimDesc']" :value="$klaim['klaimId']"
    name="klaimId" wire:model.live="klaimId" :disabled="$isFormLocked" />
```

---

## Pola Standar per Konteks

### Footer Modal

```blade
<div class="sticky bottom-0 z-10 px-6 py-4 bg-white border-t border-gray-200 dark:bg-gray-900 dark:border-gray-700">
    <div class="flex justify-between gap-3">
        {{-- Kiri: navigasi --}}
        <a href="{{ route('master.pasien') }}" wire:navigate>
            <x-ghost-button type="button">
                <svg>{{-- ikon user --}}</svg>
                Master Pasien
            </x-ghost-button>
        </a>

        {{-- Kanan: batal + simpan --}}
        <div class="flex gap-3">
            <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
            <x-primary-button wire:click.prevent="save()" class="min-w-[120px]"
                wire:loading.attr="disabled" :disabled="$isFormLocked">
                <span wire:loading.remove>Simpan</span>
                <span wire:loading><x-loading /> Menyimpan...</span>
            </x-primary-button>
        </div>
    </div>
</div>
```

### Header Modal (Close X)

```blade
<x-icon-button color="gray" type="button" wire:click="closeModal">
    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd"
            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
            clip-rule="evenodd" />
    </svg>
</x-icon-button>
```

### Area SEP / BPJS

```blade
<div class="flex flex-wrap items-center gap-2">
    {{-- Kelola SEP --}}
    <x-info-button type="button" wire:click="openVclaimModal" class="gap-2 text-xs">
        <svg>{{-- ikon dokumen --}}</svg>
        Kelola SEP BPJS
    </x-info-button>

    {{-- Cetak SEP (ikon saja) --}}
    @if (!empty($data['sep']['noSep']))
        <x-icon-button color="blue" type="button" wire:click="cetakSEP" title="Cetak SEP">
            <svg>{{-- ikon printer --}}</svg>
        </x-icon-button>
    @endif
</div>
```

### Tabel: Tombol Hapus di Baris Data

```blade
{{-- Data penting (master, transaksi) → confirm-button --}}
<x-confirm-button variant="danger" :action="'requestDelete(\'' . $row->id . '\')'"
    title="Hapus Data" :message="'Yakin hapus ' . $row->name . '?'"
    confirmText="Ya, hapus" cancelText="Batal"
    class="px-2 py-1 text-xs">
    Hapus
</x-confirm-button>

{{-- Hapus item obat / baris di tabel form (eresep, administrasi obat,
     resiko jatuh) → x-outline-button merah-tint + wire:confirm. STANDAR. --}}
<x-outline-button type="button"
    wire:click.prevent="removeItem('{{ $id }}')"
    wire:confirm="Hapus item ini?"
    wire:loading.attr="disabled"
    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700
           hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30
           dark:hover:!bg-red-900/30 dark:hover:!text-red-300"
    title="Hapus item">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
</x-outline-button>
```

> **Jangan** pakai raw `<button class="text-red-600 ...">` atau `<x-icon-button>`
> untuk tombol sampah di tabel — selalu `x-outline-button` merah-tint di atas
> agar ukuran, hover, focus-ring, dan dark-mode seragam. Referensi:
> `transaksi/ri/eresep-ri/⚡eresep-ri-non-racikan.blade.php` & `…-racikan.blade.php`.

### Modul Dokumen: gate role Hapus & Buka Kunci (TERPUSAT)

Aksi sensitif di **modul dokumen** (formulir bertanda tangan: consent, laporan,
pengkajian, dll) — **Hapus** entri & **Buka Kunci** (cabut TTD) — WAJIB digate lewat
**Gate terpusat**, bukan `@hasanyrole` literal. Daftar role ada di **satu file**
`App\Support\AksiRole` (konstanta `DOKUMEN_HAPUS` & `DOKUMEN_BUKA_KUNCI`), didaftarkan
sebagai Gate `dokumen.hapus` & `dokumen.bukaKunci` di `AppServiceProvider::boot()`.

```blade
{{-- Buka Kunci --}}
@can('dokumen.bukaKunci')
    <x-confirm-button action="bukaKunci('{{ $rowKey }}')" title="Buka Kunci ..."
        message="TTD petugas dicabut & entri kembali draft. Lanjutkan?"
        confirmText="Ya, Buka Kunci">Buka Kunci</x-confirm-button>
@endcan

{{-- Hapus --}}
@can('dokumen.hapus')
    <x-outline-button type="button" wire:click.prevent="hapus('{{ $rowKey }}')"
        wire:confirm="Yakin hapus ... ini?" wire:loading.attr="disabled"
        class="!text-red-600 !bg-red-50 !border-red-200 ...">{{-- ikon tong sampah --}}</x-outline-button>
@endcan
```

```php
// Guard server (DUA lapis) — statement PERTAMA di method hapus/removeEntry/dll:
if (!auth()->user()?->can('dokumen.hapus')) {
    $this->dispatch('toast', type: 'error', message: 'Anda tidak berwenang menghapus entri.');
    return;
}
// bolehBukaKunci(): return (bool) auth()->user()?->can('dokumen.bukaKunci');
```

> Menambah/mengurangi role yang boleh Hapus/Buka Kunci = ubah **1 baris** di
> `App\Support\AksiRole` — otomatis berlaku di SEMUA modul (RI/UGD/RJ).
> **DILARANG** menulis `@hasanyrole('Admin|Manager Umum|Manager Medis')` atau
> `hasAnyRole([...])` literal di file modul dokumen. Domain lain (administrasi, EMR
> unlock, log-aktivitas, saldo-kas) punya kebijakan sendiri — jangan pakai gate `dokumen.*`.

---

## Toolbar Aksi Berwarna (Footer EMR / Modal Multi-Aksi)

Pengecualian terkontrol dari aturan "satu primary" & "jangan override": ketika
satu area berisi **beberapa aksi sederajat** (toolbar shortcut di footer EMR,
panel aksi resep), tiap aksi dibedakan dengan **warna semantik** agar cepat
dikenali. Basisnya tetap `<x-primary-button>` (ukuran/ring/dark-mode seragam),
warnanya di-override per-semantik dengan utility `!important`.

Template override solid (ganti `{c}` dengan nama warna, amber pakai `500/600`):

```blade
class="gap-1 !bg-{c}-600 hover:!bg-{c}-700 !text-white focus:!ring-{c}-300
       dark:!bg-{c}-600 dark:!text-white dark:hover:!bg-{c}-700 dark:focus:!ring-{c}-900"
```

### Palet semantik (WAJIB diikuti — aksi sama = warna sama lintas modul)

| Semantik aksi | Warna | Override | Contoh |
|---|---|---|---|
| Aksi utama / Cetak / brand | brand | *(default `x-primary-button`)* | Buka E-Resep, Cetak e-Resep, Simpan |
| Kirim / konfirmasi positif | emerald | `!bg-emerald-600` | i-Care, TTD & Kirim ke Apotek |
| Ubah / transfer (reversible) | amber | `!bg-amber-500` | Pindah Kamar, Edit Resep (batal TTD) |
| Duplikat / Dokumen | indigo | `!bg-indigo-600` | Dokumen, Salin ke Resep Baru |
| Administrasi / kirim antar-modul | teal | `!bg-teal-600` | Administrasi, Kirim ke Plan CPPT |
| Dokumen final / Resume | rose | `!bg-rose-600` | Resume Medis |
| Destruktif / Hapus | merah-tint | `x-outline-button` merah (lihat bawah) | Hapus Draft |
| Netral / Tutup / Batal | abu | `x-secondary-button` | Tutup |

Tombol **Hapus** sengaja tidak solid penuh agar tidak menyaingi aksi positif —
pakai `x-outline-button` merah-tint:

```blade
<x-outline-button
    class="!text-red-600 !bg-red-50 !border-red-200 hover:!bg-red-100 hover:!text-red-700
           hover:!border-red-300 dark:!text-red-400 dark:!bg-red-900/20 dark:!border-red-800/30
           dark:hover:!bg-red-900/30 dark:hover:!text-red-300">
    Hapus ...
</x-outline-button>
```

**Referensi implementasi:** footer `transaksi/ri/emr-ri/⚡emr-ri.blade.php`
(i-Care=emerald, Pindah Kamar=amber, Dokumen=indigo, Administrasi=teal,
E-Resep=brand, Resume=rose) & panel aksi `transaksi/ri/eresep-ri/⚡eresep-ri.blade.php`
(TTD & Kirim=emerald, Edit=amber, Salin=indigo, Plan CPPT=teal, Cetak e-Resep=brand,
Hapus Draft=merah-tint).

> Saat menambah tombol baru di toolbar: cari makna aksinya di tabel dan pakai
> warna yang sama. Jangan kenalkan warna baru tanpa menambahkannya ke tabel ini.

---

## Aturan

1. **Satu `<x-primary-button>` per modal/form** — hanya untuk aksi utama (Simpan/Submit). *Pengecualian:* toolbar aksi sederajat (lihat bagian di atas).
2. **Jangan pakai primary untuk navigasi** — pakai `ghost-button` atau `outline-button`
3. **Close X selalu `<x-icon-button color="gray">`** — bukan secondary
4. **Aksi BPJS/SEP selalu `<x-info-button>`** — biru, langsung terlihat beda
5. **Hapus data penting selalu lewat `<x-confirm-button>`** — jangan langsung eksekusi
6. **Jangan pakai `wire:confirm`** (browser native) untuk hapus — pakai `<x-confirm-button>`
7. **Jangan pakai `!important` override untuk tombol tunggal** — pilih komponen yang tepat. Override `!bg-*` hanya untuk **toolbar aksi berwarna** sesuai palet semantik di atas.

---

## Hierarki Visual

Dari paling menonjol ke paling ringan:

```
primary-button    → Solid hijau       → Aksi UTAMA
info-button       → Solid biru        → BPJS / SEP
success-button    → Solid lime        → Serah obat, selesai
warning-button    → Solid kuning      → Peringatan
danger-button     → Solid merah       → Destruktif ringan
secondary-button  → Abu-abu + border  → Batal, Reset
outline-button    → Tint hijau        → Tab, alternatif
ghost-button      → Tint tipis        → Navigasi, link
icon-button       → Transparan kotak  → Ikon (Close, Cetak)
confirm-button    → Multi-variant     → Aksi butuh konfirmasi
```

---

## x-toolbar-refresh-reset — Tombol standar toolbar list (Refresh + Reset)

Button group menyatu untuk SEMUA halaman list bertabel (daftar, antrian kasir,
pelayanan, penunjang, dsb.). Tinggi selaras `x-primary-button` (`py-2.5 text-sm`).

```blade
{{-- toolbar dengan kolom berlabel (Tanggal, Status, ...) — label "Aksi" sejajar --}}
<x-toolbar-refresh-reset class="ml-auto" />

{{-- toolbar yang elemen kanannya tak berlabel --}}
<x-toolbar-refresh-reset :label="null" />

{{-- method reset custom --}}
<x-toolbar-refresh-reset reset-action="resetSemua" />

{{-- data disimpan di properti (hasil API), harus ditarik ulang — bukan cuma render --}}
<x-toolbar-refresh-reset :label="null" refresh-action="muatPermintaan" />
```

Perilaku:
- **Refresh** (biru, ikon reload berputar saat loading) = `$refresh` Livewire —
  muat ulang data **tanpa mengubah parameter filter**. `$refresh` hanya me-render ulang:
  cukup untuk list `#[Computed]`, TIDAK untuk data yang disimpan di properti (hasil API,
  mis. kotak masuk Rujukan Masuk) — di situ isi `refresh-action` dengan method penarik datanya.
- **Reset** (abu, ikon panah balik) = panggil `resetFilters` (atau `reset-action`)
  di komponen pemakai — kembalikan semua filter ke kondisi awal.

Jangan bikin tombol refresh/reset manual lagi — ikon reload HANYA untuk refresh,
panah balik HANYA untuk reset (jangan tertukar).

## Tombol aksi per entri (2026-09-08)

Cetak dan hapus per entri di tabel **wajib** komponen: `<x-cetak-button>` dan `<x-hapus-button>`
(ikon 40px, sekelas tombol berteks). Rincian, mode dialog modal, dan pengecualian: lihat
`docs/standar-ui-komponen.md` bagian "Tombol aksi per entri di tabel".


---

## Ikon Tombol — `<x-ikon nama="…">` (2026-11-17)

**Setiap tombol berteks memakai ikon di kiri teks, dan aksi yang sama memakai ikon yang sama
di seluruh project.** Peta ikon hanya ada di satu tempat, yaitu `App\Support\IkonTombol::PETA`.
Komponen `resources/views/components/ikon.blade.php` merender dari peta itu. Katalog visualnya
tampil di `/panduan-dev` (Komponen → Ikon Tombol).

```blade
<x-secondary-button type="button" wire:click="closeModal">
    <x-ikon nama="batal" />
    Batal
</x-secondary-button>

<x-confirm-button variant="primary" :action="'simpan()'" title="…" message="…">
    <x-ikon nama="simpan" />
    Simpan Perubahan
</x-confirm-button>

{{-- tombol dengan state loading: ikon ikut di span wire:loading.remove --}}
<x-primary-button type="button" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">
    <span wire:loading.remove wire:target="simpan" class="inline-flex items-center gap-2">
        <x-ikon nama="simpan" />
        Simpan
    </span>
    <span wire:loading wire:target="simpan"><x-loading /> Menyimpan...</span>
</x-primary-button>

{{-- tombol kecil (!text-xs): ukuran lewat prop, BUKAN class --}}
<x-ikon nama="kembalikan" ukuran="w-3.5 h-3.5" />
```

### Katalog (25 aksi)

| `nama` | Ikon | Dipakai untuk label |
|---|---|---|
| `simpan` | disket | Simpan, Simpan Perubahan/Koreksi/Draft, Simpan & Posting, Perbarui |
| `batal` | ✕ | Batal, Tutup, Batal Edit, Tidak: menutup form/modal **tanpa menyimpan** |
| `tambah` | ＋ | Tambah …, Buat, Order, Catat …, Transaksi Baru (buang tanda "+" dari teks) |
| `ubah` | pensil | Ubah, Ubah Data, Edit, Ganti, Koreksi, Lanjut |
| `hapus` | tong sampah | Hapus data |
| `batalkan` | lingkaran dicoret | Batalkan transaksi/order/SEP, Batal Inap, Cabut, Unmount: membatalkan yang **sudah tercatat** |
| `kembalikan` | panah balik | Kembalikan ke Antrian, Kosongkan Form, Reset, Bersihkan, Batal / Entri Baru |
| `kembali` | panah kiri | Kembali ke Daftar, Kembali ke Entri Baru (navigasi mundur) |
| `muat-ulang` | panah melingkar | Muat Ulang, Tarik Data, Sinkron, Grouping Ulang, Salin Ulang |
| `lihat` | mata | Lihat, Lihat Hasil/Foto, Preview, Pas Lebar/Layar |
| `cari` | kaca pembesar | Cari, Cek (lookup master/BPJS) |
| `kirim` | pesawat kertas | Kirim ke BPJS/SATUSEHAT/SISRUTE/DINKESTA, Post, TaskId |
| `unggah` | panah naik ke baki | Upload, Unggah, Import, Replace berkas |
| `unduh` | panah turun ke baki | Unduh, Unduh Excel, Download, Export |
| `setujui` | centang dalam lingkaran | Selesai, Konfirmasi, Review, Setujui, Telaah, Saya Mengerti, Daftarkan, Pilih |
| `ttd` | pena | TTD, TTD-E, Tanda Tangan, Saya |
| `kunci` | gembok tertutup | Kunci |
| `buka-kunci` | gembok terbuka | Buka Kunci, Akses Darurat |
| `proses` | petir | Proses, Generate, Gabung jadi 1 PDF, Hitung, Grouping |
| `hentikan` | kotak dalam lingkaran | Hentikan, Stop, Kill Session |
| `dokumen` | kertas bergaris | Modul Dokumen, E-Resep, Kelola, Rekam Medis |
| `riwayat` | papan klip | Log, Log Aktivitas, Riwayat |
| `pembayaran` | dompet | Administrasi, Bayar, Kasir |
| `waktu` | jam | Jam Sekarang, Hari Ini (isian tanggal tetap pakai `x-now-button`) |
| `cetak` | printer | Print Etiket / cetak langsung via print agent. Tombol cetak biasa tetap `x-cetak-button` |

### Aturan

1. **Jangan tulis `<svg>` inline di tombol berteks.** Pakai `<x-ikon>`. Aksi yang belum ada di
   peta → tambah entri di `IkonTombol::PETA` dulu (nama = kata kerja bahasa Indonesia, kebab-case,
   ditulis penuh), lalu catat di tabel ini.
2. **Satu nama = satu makna.** Tiga yang sering tertukar:
   `batal` (✕, tutup tanpa simpan) ≠ `batalkan` (⊘, membatalkan transaksi tercatat) ≠ `hapus` (tong sampah).
3. **Ikon di kiri teks**, ukuran default `w-4 h-4`. Tombol kecil `!text-xs` → `ukuran="w-3.5 h-3.5"`.
4. **Komponen yang sudah berikon dipakai apa adanya**, jangan ditambah `<x-ikon>`:
   `x-hapus-button`, `x-cetak-button`, `x-lihat-button`, `x-action-edit`, `x-now-button`,
   `x-toolbar-refresh-reset`, `x-satu-sehat.ikon-tombol`.
5. **Tombol hanya-ikon** (Close X di header, hapus baris kecil) tetap `<x-icon-button>`, dan isinya
   boleh `<x-ikon nama="…" ukuran="w-5 h-5" />`.
6. **Nama salah ketik** tidak merender ikon dan tercatat di `storage/logs` (`InvalidArgumentException`).
   Kalau ikon tidak muncul, cek log.

### Komponen & warna per aksi (2026-11-17)

Ikon dan komponen tombol satu paket: kolom `tombol` di `IkonTombol::PETA` adalah standarnya,
dan `/panduan-dev#ikon-tombol` menampilkannya. Ditetapkan dari pemindaian 1.236 tombol berteks
(komponen terbanyak per aksi) + aturan yang sudah ada di dokumen ini.

**Prinsip:**
1. Komponen ditentukan **makna aksi**, bukan selera per layar.
2. Aksi positif (simpan, tambah, kirim, setujui, proses, unggah, ttd): yang **utama** di satu panel
   memakai komponen solid; yang **pendamping** di panel yang sama memakai `x-secondary-button`.
   Satu panel/form = satu tombol solid utama.
3. **Menulis ke BPJS** (Kirim, Daftarkan ke Aplicares, Update SEP) = `x-info-button` (biru).
   **Menulis ke SATUSEHAT** (Kirim, Daftarkan UUID Location) = `x-primary-button` teal
   (`class="!bg-teal-600 hover:!bg-teal-700"`). Membaca/menarik data dari keduanya = `muat-ulang`.
4. Hapus/batalkan selalu lewat `x-confirm-button`, jangan pakai `wire:confirm` (lihat Aturan no. 6).

| Aksi | Komponen standar |
|---|---|
| `simpan` | `x-primary-button`; perlu konfirmasi → `x-confirm-button variant="primary"` |
| `batal`, `kembali` | `x-secondary-button` |
| `tambah` | toolbar daftar (buka form baru) → `x-primary-button`; tambah baris/sub-item di dalam form → `x-secondary-button` |
| `ubah` | `x-secondary-button`; baris tabel → `x-action-edit` |
| `hapus` | `x-confirm-button variant="danger"`; tombol kecil di dalam tabel/form → `variant="danger-soft"`; baris tabel → `x-hapus-button` |
| `batalkan` | `x-confirm-button variant="danger"` |
| `kembalikan` | `x-secondary-button`; bila mengubah data tersimpan (Reset Status Kunci, Kembalikan ke Antrian) → `x-confirm-button variant="warning-soft"` |
| `buka-kunci` | `x-confirm-button variant="warning-soft"` |
| `kunci` | `x-confirm-button variant="primary"` |
| `hentikan` | `x-danger-button` |
| `muat-ulang` | `x-secondary-button`; aksi utama satu-satunya di panel → `x-primary-button` |
| `cari`, `lihat`, `unduh`, `riwayat` | `x-outline-button` (lihat di baris tabel → `x-lihat-button`) |
| `kirim`, `setujui` | utama → `x-primary-button` (BPJS → `x-info-button`, SATUSEHAT → teal); pendamping → `x-secondary-button` |
| `proses` | utama → `x-primary-button` (grouping/klaim BPJS → `x-info-button`); pembantu isian (Generate kode) → `x-outline-button` |
| `unggah`, `ttd` | `x-primary-button` (pendamping → `x-secondary-button`) |
| `dokumen`, `pembayaran` | toolbar EMR → palet semantik (indigo / teal); lainnya → `x-outline-button` |
| `waktu` | isian tanggal → `x-now-button` |
| `cetak` | `x-cetak-button`; cetak khusus yang tak bisa memakainya (print agent etiket) → `x-secondary-button` + `<x-ikon nama="cetak">` |

**Pengecualian yang disengaja:** tombol navigasi ke modul lain (`<a wire:navigate>` + Pendaftaran
RJ/UGD/RI) tetap `x-outline-button`; tombol pilihan dari daftar (isi kode + nama) tanpa ikon;
tombol kecil bergaris putus "Tambah Bed" di kartu kamar.

**Penerapan:** Master selesai 2026-11-17 (13 tombol diganti komponennya: Hapus Semua Jadwal →
danger; Hentikan ×2 → danger-button; Muat Ulang footer, Tarik Data Aplicares/SIRS → secondary;
Daftarkan ke Aplicares ×2 → info; Daftarkan UUID kamar → teal; Tambah SLKI/SIKI → secondary;
Reset Status Kunci → confirm warning-soft; di tabel Aplicares: Hapus per baris → danger-soft,
Samakan → secondary). 25 ikon SVG manual di Master juga diganti `<x-ikon>`, jadi Master kini 100%
memakai katalog (164 tombol; 2 tombol pilihan Kamar sengaja tanpa ikon).
- 2026-11-17 **Keuangan** (34 tombol): 26 ikon dipasang, 6 SVG manual diganti, Unduh Data Excel
  info → outline, Evaluasi (analisa perputaran obat) secondary → outline. "Transaksi Baru" di
  pembayaran hutang/topup = mengosongkan supplier → ikon `kembalikan`. Tombol ◀ ▶ pindah bulan
  (Buku Besar, Laba Rugi) sudah berupa simbol, dibiarkan.
- 2026-11-17 **Gudang** (45 tombol, sebelumnya 0 berikon): 37 ikon dipasang, 8 SVG manual diganti.
  Komponen: baris tabel Edit/Ubah Data → secondary, Lihat/Lihat Data → outline, Hapus → danger-soft;
  "+ Masukkan" (tambah baris barang) → secondary; Simpan Sementara (pendamping Proses Stok) → secondary.
- 2026-11-17 **Penunjang** (Lab, Radiologi, Kamar Operasi; 53 tombol, sebelumnya 0 berikon): 27 ikon
  dipasang, 26 SVG manual diganti. Ikon baru `cetak` untuk Print Etiket (print agent). Import Hasil
  Mindray: warna biru buatan sendiri dibuang → secondary, ikon `muat-ulang`. Pengecualian: Detail
  Pemeriksaan (satu-satunya aksi di baris worklist Lab) tetap primary; pemicu menu titik-3 dilewati.
- 2026-11-17 **RJ — ikon saja** (198 tombol): 101 ikon dipasang, 62 SVG manual diganti; 166 tombol
  kini `<x-ikon>`, 28 memakai `x-satu-sehat.ikon-tombol`. Dilewati (SVG khas dipertahankan): Master
  Pasien (tautan), Scan Wajah, toggle Auto-Print, teks loading "Mengupload…". **Komponen RJ BELUM
  disamakan** — 15 usulan (Simpan Telaah, TTD-E Telaah Obat, SKDP/PRB ke BPJS → info, Buka Kunci →
  warning-soft, dst.) + 2 perbaikan ikon (Batal Task 99 → `batalkan`, buang ikon di "Lanjut ke … →")
  + keputusan warna "Administrasi" (teal sesuai palet vs ungu di Apotek RJ/Kasir RI) menunggu user.
- 2026-11-17 **UGD — ikon saja** (203 tombol): 98 ikon dipasang, 71 SVG manual diganti; 170 tombol
  kini `<x-ikon>`, 29 memakai `x-satu-sehat.ikon-tombol`. Tanpa ikon (disengaja): pilihan level dokter
  "Utama"/"RG" di Form Transfer UGD→RI, tautan Master Pasien, teks loading "Mengupload…".
  **Komponen UGD BELUM disamakan** — usulan sejenis RJ (Simpan Telaah, TTD-E Telaah Obat, Rujukan ke
  BPJS → info, Buka Kunci ×2 → warning-soft, Reset/Bersihkan/Tambah Implementasi → secondary) plus
  khusus UGD: Cari Faskes & Cek BPJS → outline, Tambah Pelayanan/Alat Bantu (discharge planning)
  ghost → secondary, Review DPJP / Batal Review (SBAR) tanpa warna buatan sendiri. Menunggu user.

Area lain menyusul per modul: pasang ikon + samakan komponen + ganti SVG manual sekaligus.

### Status penerapan (pemindaian 2026-11-17)

Dari 1.648 tombol di `resources/views`, **700 tombol berteks di 375 file belum berikon**
(halaman `auth/` & `profile/` bawaan Breeze tidak dihitung). Pemetaan otomatis label → `nama`:
batal 198 · simpan 93 · tambah 81 · ubah 55 · proses 33 · setujui 30 · kirim 25 · kembalikan 23 ·
muat-ulang 21 · batalkan 20 · unggah 20 · lihat 17 · kembali 14 · cari 12 · hentikan 9 · ttd 8 ·
hapus 6 · waktu 5 · buka-kunci 3 · dokumen 2 · riwayat 1 · **belum terpetakan 24** (label khusus:
navigasi ◀ ▶, "Ambil IHS", "Pakai Pengganti", "Rawat Gabung", dll. Putuskan per kasus).
Per area: RI 115, RJ 101, UGD 99, komponen 70, Gudang 37, Penunjang 27, Keuangan 26, Master ±150.

Penerapan dilakukan **per modul** (bukan sapuan sekaligus):
- 2026-11-17 Kasir RJ/UGD/RI + Kas TU (13 tombol), sudah.
- 2026-11-17 **Master** (139 tombol, 66 file, 27 modul), sudah. Sengaja tanpa ikon: 2 tombol pilihan
  kelas/TT di daftar Master Kamar (isinya kode + nama, bukan aksi). Saat menyentuh sebuah halaman, pasang ikon pada tombol-tombolnya sesuai tabel.
