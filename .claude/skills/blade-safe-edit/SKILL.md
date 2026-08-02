---
name: blade-safe-edit
description: Aturan keselamatan saat mengedit file Blade / Volt di repo ini. Baca sebelum melakukan edit bulk atau pakai sed/perl/regex pada *.blade.php — mencegah match melebar yang merusak banyak file. Juga mencakup jebakan compiler Volt.
---

# Blade Safe Edit

File Blade di repo ini besar dan banyak nested tag. Edit ceroboh gampang merusak banyak file sekaligus.

## 1. JANGAN regex multiline untuk Blade
`perl -0` / `sed` multiline rawan match melebar dan merusak banyak file diam-diam.

- Pakai tool **Edit** dengan `old_string` presisi (sertakan konteks unik).
- Untuk perubahan berulang yang identik, pakai `replace_all: true` pada Edit — bukan sed.

## 2. Verifikasi sesudah edit — `php -l` TIDAK cukup
`php -l` lolos walau struktur tag Blade kacau. Selalu cek:

```bash
git diff --stat                 # pastikan jumlah file/baris berubah masuk akal
# hitung balance tag yang diedit, mis. modal/div pembuka vs penutup
grep -c '@if' file.blade.php; grep -c '@endif' file.blade.php
grep -c '<x-modal' file.blade.php; grep -c '</x-modal' file.blade.php
```
Diff-stat yang membengkak = tanda match melebar → batalkan.

## 2b. Komentar Blade WAJIB seimbang — teks bocor ke layar

Mengedit ISI komentar `{{-- --}}` yang panjang gampang menyisipkan `--}}` di tengah.
Sisa komentarnya lalu jadi **teks biasa yang tercetak ke layar**, lengkap dengan `--}}`.
`php -l` lolos (hasil kompilasinya PHP yang sah), dan uji render yang cuma memeriksa
"apakah teks X ada" juga lolos — yang bocor justru teks yang tidak dicari siapa pun.

Kejadian nyata 2026-08-02: komentar lebar kolom di `master-dokter-penggajian-actions`
diganti isinya, `--}}` ikut tertulis di akhir teks baru, sementara paragraf lama masih
menyusul sesudahnya. Panel "Gaji Pokok & Skema" menampilkan kalimat tentang
`x-text-input-number` ke pengguna.

Cek setelah menyentuh komentar mana pun:

```bash
# jumlahnya harus sama persis
grep -c '{{--' file.blade.php; grep -c -- '--}}' file.blade.php
```

Lebih tuntas — pastikan hasil render tidak memuat penanda komentar sama sekali:

```php
$html = Livewire::test('...')->call('open', $id)->html();
assert(!str_contains($html, '--}}') && !str_contains($html, '{{--'));
```

Saat mengganti isi komentar panjang, sertakan `--}}` penutup lama di dalam
`old_string` supaya tidak mungkin tertinggal dua penutup.

## 3. Volt: hindari kata "use" di komentar PHP
Compiler Volt salah-strip komentar `//` bila ada substring `re-use` / `reuse` — sisanya terbaca sebagai statement `use` → **ParseError**.

```php
// SALAH di blok <?php Volt:  // re-use komponen ini
// BENAR: tulis ulang tanpa "use", mis. "pakai ulang komponen ini"
```

## 4. Pola UI sudah terdokumentasi — jangan reinvent
Sebelum bikin komponen, cek `docs/` (lihat skill `ui-pattern-docs`): tombol standar, now-button, print PDF/TTD, page-frame, dirty-modal, stable-lookup, tinymce. Ikuti pola yang ada agar konsisten.

## Escape ganda pada prop komponen (tampil `&amp;` di layar)

Prop yang di-echo ulang komponen dengan `{{ }}` (`value` pada `x-input-label`, `title` pada
`x-border-form`, `nameLabel`/`signLabel`/`emptyText` pada `x-signature.ttd-petugas`, dll.)
akan ter-escape DUA kali:

```blade
{{-- SALAH — layar menampilkan "Mata &amp; Telinga" --}}
<x-input-label value="Mata &amp; Telinga" />
<x-input-label value="{{ $organ['label'] }}" />   {{-- label ber-& juga kena --}}

{{-- BENAR --}}
<x-input-label value="Mata & Telinga" />
<x-input-label :value="$organ['label']" />
```

`&amp;` tetap benar untuk teks HTML biasa (paragraf, isi tombol). Yang dilarang hanya di
ATRIBUT prop komponen. Cek cepat sebelum lapor:

```bash
grep -n '(value|title|nameLabel|signLabel)="[^"]*&amp;' file.blade.php   # harus kosong
grep -c '&amp;amp;' file.blade.php                                        # harus 0
```
