<?php

use Livewire\Component;

// Tutorial standar STRUKTUR FOLDER & PENAMAAN BERKAS — versi web dari
// docs/standar-struktur-folder.md. Halaman referensi (tanpa state form):
// snippets() hanya mengembalikan teks contoh (nowdoc), dirender apa adanya
// ke dalam <pre> sehingga sintaks Blade di dalamnya tampil sebagai teks.
new class extends Component {
    // Token %TAG_KOMPONEN% disulih jadi tag komponen anonim saat runtime.
    // Ditulis begini karena ComponentTagCompiler menyisir SELURUH isi berkas SFC —
    // nowdoc di blok kelas ini ikut kena, jadi literalnya dikira komponen betulan
    // dan membuat `php artisan view:cache` gagal. Jebakan yang dibahas bab ini juga.
    private const TAG_KOMPONEN = '<' . 'x-...>';

    public function snippets(): array
    {
        $snippets = [

'peta-views' => <<<'TXT'
resources/views/
├── components/                  # KOMPONEN BLADE ANONIM  %TAG_KOMPONEN%
│   │                            #   TANPA state, TANPA kelas Volt
│   ├── <nama>.blade.php         #   umum lintas modul: modal, text-input, now-button
│   └── <namespace>/             #   berkelompok: list.*, pdf.*, signature.*, lov.*
│
├── layouts/                     # layouts::app, layouts::guest, layouts::app-fullscreen
│
├── livewire/                    # KOMPONEN LIVEWIRE LINTAS-MODUL
│   └── lov/<entitas>/           #   35 LOV — acuan konsistensi terbaik di repo
│
└── pages/                       # HALAMAN & KOMPONEN MILIK HALAMAN  (namespace pages::)
    ├── components/              #   komponen pages LINTAS-JALUR (dipakai >1 area)
    │   ├── modul-dokumen/       #     cetakan + modal dokumen  (bpjs/ ri/ rj/ ugd/)
    │   ├── rekam-medis/         #     viewer & cetakan rekam medis
    │   └── manajemen/           #     cetakan laporan manajemen
    └── <area>/                  #   master, transaksi, manajemen, database-monitor…
        └── <modul>/             #     satu layar = satu folder
            ├── ⚡<modul>.blade.php
            ├── ⚡<modul>-actions.blade.php
            └── <modul>-<bagian>.blade.php
TXT,

'peta-app' => <<<'TXT'
app/
├── Console/Commands/          # perkakas: MakeLov, BersihkanCacheSnomed
├── Http/
│   ├── Controllers/           # HANYA sisa Breeze (auth, profile).
│   │                          # Fitur baru = Volt SFC, BUKAN controller
│   ├── Middleware/  Requests/
│   └── Traits/                # mixin untuk komponen Livewire — butuh $this
│       ├── Concerns/          #   lintas-komponen, bukan domain
│       ├── BPJS/ SATUSEHAT/ SIRS/ IDRG/
│       ├── Txn/<Jalur>/  Manajemen/<Sumber>/<Unit>/  Master/<Modul>/
│       └── Dokumen/ Keuangan/ Stock/
├── Models/                    # Eloquent hanya untuk tabel milik Laravel.
│                              # Tabel Oracle warisan → Query Builder
├── Providers/  Services/
├── Support/                   # class stateless, semua method static
│   ├── Clause/        (6)     #   teks legal berversi
│   ├── Options/       (9)     #   daftar opsi & skala formulir EMR
│   ├── Terminologi/   (8)     #   pemetaan SNOMED/KFA/LOINC/ICD/FHIR
│   ├── GajiDokter/    (2)     #   modul slip gaji
│   ├── Downtime/      (2)     #   formulir & tarif waktu henti
│   └── <11 berkas di akar>    #   pembantu tunggal per domain
└── View/Components/           # AppLayout, GuestLayout
TXT,

'cek-bolt' => <<<'TXT'
cd resources/views

# Harus kosong: SFC Volt yang belum ber-⚡
for f in $(find . -name '*.blade.php' ! -name '⚡*'); do
  grep -qE '^new .*class extends' "$f" && echo "KURANG ⚡: $f"
done

# Harus kosong: berkas ber-⚡ yang ternyata partial
for f in $(find . -name '⚡*.blade.php'); do
  grep -qE '^new .*class extends' "$f" || echo "SALAH ⚡: $f"
done
TXT,

'suffix-jalur' => <<<'TXT'
pages/transaksi/ri/emr-ri/modul-dokumen/pengkajian-pre-op-ri/
    ⚡rm-pengkajian-pre-op-ri-actions.blade.php

pages/transaksi/rj/emr-rj/modul-dokumen/pengkajian-pre-op-rj/
    ⚡rm-pengkajian-pre-op-rj-actions.blade.php

pages/transaksi/ugd/emr-ugd/modul-dokumen/pengkajian-pre-op-ugd/
    ⚡rm-pengkajian-pre-op-ugd-actions.blade.php

# Pengecualian anti-stutter: nama sudah memuat jalurnya
pages/transaksi/ugd/emr-ugd/modul-dokumen/form-trf-ugd-ri/
    ⚡rm-form-trf-ugd-ri-actions.blade.php     ← BUKAN ...-ri-ugd-actions
TXT,

'jalur-transaksi' => <<<'TXT'
pages/transaksi/{rj,ugd,ri,ri-resep}/
├── daftar-<jalur>/          # pendaftaran (LIST + *-actions per integrasi)
├── daftar-<jalur>-bulanan/
├── pelayanan-<jalur>/
├── administrasi-<jalur>/    # 1 berkas per pos biaya: room-ri, visit-ri, konsul-ri…
├── eresep-<jalur>/
├── emr-<jalur>/
│   ├── ⚡emr-<jalur>.blade.php          # shell EMR + tab
│   ├── <tab>-<jalur>/                  # pengkajian-awal-ri, penilaian-ri, cppt-ri…
│   └── modul-dokumen/<modul>-<jalur>/
├── idrg/  satu-sehat/  task-id-pelayanan/
└── display-pasien-<jalur>/
TXT,

'route-pola' => <<<'TXT'
Route::livewire('/<area>/<modul>', 'pages::<area>.<modul>.<modul>')
    ->name('<area>.<modul>');

// Tiga hal HARUS sejalan:
//   segmen URL pertama  =  folder area  =  prefix nama route

// Kata "transaksi" TIDAK muncul di URL — yang muncul jalur/fungsinya:
Route::livewire('/ri/daftar', 'pages::transaksi.ri.daftar-ri.daftar-ri')
    ->name('ri.daftar');
Route::livewire('/penunjang/laborat', 'pages::transaksi.penunjang.laborat.daftar-laborat')
    ->name('penunjang.laborat');

// Nama modul tidak mengulang jalurnya di URL:
//   /ri/update-tt        BENAR
//   /ri/update-tt-ri     SALAH  (nama BERKAS tetap update-tt-ri)

// Mengubah URL yang sudah live? Sertakan redirect — bookmark petugas.
Route::redirect('/rawat-jalan/daftar', '/rj/daftar');   // 302, bukan 301
TXT,

'pecah-partial' => <<<'TXT'
# SEBELUM — satu berkas 1.782 baris
⚡rm-pengkajian-pre-op-ri-actions.blade.php

# SESUDAH — induk 1.099 + 7 partial per section
⚡rm-pengkajian-pre-op-ri-actions.blade.php          1.099   (858 = blok kelas Volt)
rm-pengkajian-pre-op-ri-data-operasi.blade.php         45
rm-pengkajian-pre-op-ri-keadaan-pra-bedah.blade.php    81
rm-pengkajian-pre-op-ri-persiapan-pasien.blade.php    137
rm-pengkajian-pre-op-ri-persiapan-administrasi.blade.php 36
rm-pengkajian-pre-op-ri-site-marking.blade.php         88
rm-pengkajian-pre-op-ri-ttd.blade.php                  28
rm-pengkajian-pre-op-ri-daftar-tersimpan.blade.php    275

# Di induk, blok itu berganti jadi satu baris:
@include('pages.transaksi.ri.emr-ri.modul-dokumen.pengkajian-pre-op-ri.rm-pengkajian-pre-op-ri-ttd')
TXT,

'kunci-data' => <<<'TXT'
// ⚠ id panel dipakai DUA peran: nama berkas DAN nilai tersimpan di DB
//   ($marks[].view pada record pengkajian pre-op)

$groups = [
    'Tubuh' => ['panels' => [
        ['id' => 'priaFront',  'label' => 'Pria — Depan',  ...],
        ['id' => 'wanitaBack', 'label' => 'Wanita — Belakang', ...],
    ]],
];

// Nama BERKAS kebab-case; id TETAP camelCase karena sudah tersimpan.
// Nama berkas diturunkan eksplisit, jangan dibalik:
$fig = 'components.site-marking.figs.' . \Illuminate\Support\Str::kebab($p['id']);
//     priaFront  ->  components/site-marking/figs/pria-front.blade.php

// Mengubah id = tanda operasi pada record LAMA tidak lagi cocok panel mana pun.
TXT,

'trait-support' => <<<'TXT'
// TRAIT — mixin ber-state, di-use oleh kelas Volt. Boleh menyentuh $this.
namespace App\Http\Traits\Txn\Ri;

trait EmrRITrait
{
    public function muatEmr(): void
    {
        $this->dataEmr = ...;          // ← menyentuh $this  → memang Trait
        $this->dispatch('toast', ...);
    }
}

// SUPPORT — stateless, semua static, tidak tahu-menahu soal Livewire.
namespace App\Support\Terminologi;

class AlergiSnomed
{
    public static function kode(string $nama): ?string   // input → output
    {
        return self::MAP[$nama] ?? null;
    }
}

// Uji satu kalimat: butuh $this / dispatch() / properti komponen?
//   ya    → app/Http/Traits/<Grup>/
//   tidak → app/Support/<SubNamespace>/
// Trait yang tidak pernah menyentuh $this = salah tempat.
TXT,

'jebakan-satu-ns' => <<<'TXT'
// ⚠ JANGAN andalkan resolusi satu-namespace antar kelas Support.
// Sebelum penataan, 7 tempat menulis begini — jalan HANYA karena keduanya
// kebetulan sama-sama di App\Support:

namespace App\Support;

class ObatKfa
{
    public static function items(int $no): array
    {
        return EresepJson::lembar($no);   // ← tanpa use, tanpa FQCN
    }
}

// Begitu salah satunya pindah namespace, ini PUTUS — dan putusnya SENYAP:
// php -l lolos, kompilasi Blade lolos, resolver Livewire tak menjangkau.
// Baru meledak saat jalur kode itu dijalankan.

// BENAR — tulis use eksplisit, selalu:
use App\Support\EresepJson;
TXT,

'pemeriksa' => <<<'TXT'
# 1. Kompilasi Blade  — struktur directive, tag komponen, blok Volt
#    Bootstrap Laravel sampai tahap REGISTER saja (lewati BootProviders),
#    ambil blade.compiler, compileString() tiap berkas, lalu php -l hasilnya.
#    WAJIB pisahkan blok kelas SFC (sampai penutup blok PHP di baris sendiri)
#    sebelum dikompilasi — kalau tidak, %TAG_KOMPONEN% yang cuma disebut di komentar //
#    ikut disulih jadi kode komponen dan memunculkan parse error PALSU.
#
#    CATATAN: penutup blok PHP tidak ditulis literal di snippet ini — menulisnya
#    akan membuat compiler SFC Livewire memotong blok kelas halaman ini persis
#    di sini. Itu jebakan yang sama, dan sempat terjadi saat halaman ini dibuat.

# 2. Resolusi komponen Livewire
$finder = app('livewire.finder');       # BUKAN app(Finder::class) —
$finder->resolveSingleFileComponentPath($nama);   # itu instance baru & kosong

# 3. Nama view literal  (@include / view() / loadView / setView)
View::exists($nama);                    # resolver Livewire TIDAK menjangkau ini

# 4. Resolusi kelas  (rujukan Foo::)
class_exists($fqcn);                    # JANGAN di dalam Tinker — Tinker
                                        # meng-alias kelas ke global namespace

# 5. Impor trait  (use App\...Trait;)
trait_exists($fqcn);                    # pemeriksa kelas tak menjangkau ini:
                                        # trait dipakai lewat use, bukan Foo::

# 6. Nama route
Route::has($nama);

# 7. Request nyata untuk redirect
$kernel->handle(Request::create($urlLama, 'GET'));   # harus 302 ke URL baru
TXT,

'rekonstruksi' => <<<'TXT'
# Sebelum memecah berkas, dua syarat harus lolos:

# (1) IMBANG TAG di dalam tiap partial
#     div  ·  komponen x-*  ·  @if  ·  @foreach  ·  penanda komentar Blade
#     Awas: <x-text-input\n ... /> — lookahead "/>" tidak melewati newline,
#     jadi regex naif mengira itu tag pembuka.

# (2) REKONSTRUKSI BYTE-EKSAK
#     induk (tiap @include diganti KEMBALI oleh isi partial-nya)
#         ==  berkas asli, byte per byte
#
#     Ini invarian TEKSTUAL, jadi tidak bergantung pada cabang @if mana yang
#     kebetulan ikut dirender saat diuji — kelemahan verifikasi berbasis render.
#     Kalau lolos, isi keluaran render tidak mungkin berubah.

# Satu-satunya yang berubah sesudah pecah: BARIS KOSONG (@include menelan
# newline di sekitarnya). Tidak berpengaruh — letaknya antar-blok.
TXT,

        ];

        return str_replace('%TAG_KOMPONEN%', self::TAG_KOMPONEN, $snippets);
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snip = $this->snippets();

        // Sidebar per-submenu. Key = id section di partial bab.
        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'prinsip'     => '6 Prinsip',
            ],
            'resources/views' => [
                'peta-views' => 'Peta Direktori',
                'bolt'       => 'Prefix ⚡',
                'suffix'     => 'Suffix Peran & Jalur',
            ],
            'Penempatan' => [
                'area'   => 'Penempatan per Area',
                'ukuran' => 'Batas Ukuran & Cara Pecah',
            ],
            'app/ & Routing' => [
                'peta-app' => 'Peta app/ — Trait vs Support',
                'routing'  => 'Routing & URL',
            ],
            'Praktik' => [
                'pemeriksa' => '7 Pemeriksa',
                'checklist' => 'Checklist & Referensi',
            ],
        ];

        $labels = array_merge(...array_values($menuGroups));
    @endphp

    <div class="ds" style="min-height:100vh"
        x-data='{
            section: "pendahuluan",
            order: @json(array_keys($labels)),
            labels: @json($labels),
            idx() { return this.order.indexOf(this.section) },
            go(s) {
                this.section = s;
                history.replaceState(null, "", "#" + s);
                window.scrollTo({ top: 0, behavior: "smooth" });
            },
            init() {
                const h = window.location.hash.slice(1);
                if (this.order.includes(h)) this.section = h;
            }
        }'>
        <div class="ds-section" style="padding-top:32px; padding-bottom:96px">

            {{-- ============ HEADER ============ --}}
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="ds-spike"></span>
                    <span class="ds-title-sm" style="color:var(--ink)">RSI&nbsp;Madinah</span>
                    <a href="{{ route('panduan-dev') }}" wire:navigate
                        class="ds-body-sm hover:underline" style="color:var(--muted-soft)">/ Standarisasi UI</a>
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Struktur Folder &amp; Penamaan</span>
                </div>
                <x-theme-toggle />
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR (per-submenu) ============ --}}
                <aside class="self-start lg:sticky lg:top-24">
                    @foreach ($menuGroups as $group => $items)
                        <div class="mb-6">
                            <div class="ds-caption-up mb-2 px-3">{{ $group }}</div>
                            <div class="space-y-0.5">
                                @foreach ($items as $key => $label)
                                    <button type="button" x-on:click="go('{{ $key }}')"
                                        class="block w-full px-3 py-1.5 text-sm text-left rounded-lg transition-colors"
                                        :class="section === '{{ $key }}' ? 'font-semibold' : 'font-normal'"
                                        :style="section === '{{ $key }}'
                                            ? 'background:var(--surface-card); color:var(--ink)'
                                            : 'color:var(--body)'">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="px-3 pt-4" style="border-top:1px solid var(--hairline)">
                        <div class="ds-caption" style="color:var(--muted-soft)">
                            Sumber: <span class="ds-code">docs/standar-struktur-folder.md</span><br>
                            Isi berkas: <span class="ds-code">standar-master-module.md</span>
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.koding-struktur.koding-struktur-mulai')

                    @include('pages.panduan-dev.koding-struktur.koding-struktur-views')

                    @include('pages.panduan-dev.koding-struktur.koding-struktur-penempatan')

                    @include('pages.panduan-dev.koding-struktur.koding-struktur-app')

                    @include('pages.panduan-dev.koding-struktur.koding-struktur-praktik')

                    {{-- ============ NAVIGASI PREV / NEXT ============ --}}
                    <div class="flex items-center justify-between gap-3 mt-16 pt-8"
                        style="border-top:1px solid var(--hairline)">
                        <button type="button" class="ds-btn ds-btn-ghost"
                            x-show="idx() > 0" x-cloak
                            x-on:click="go(order[idx() - 1])">
                            ← <span x-text="labels[order[idx() - 1]]"></span>
                        </button>
                        <span x-show="idx() === 0"></span>
                        <button type="button" class="ds-btn ds-btn-primary"
                            x-show="idx() < order.length - 1" x-cloak
                            x-on:click="go(order[idx() + 1])">
                            <span x-text="labels[order[idx() + 1]]"></span> →
                        </button>
                    </div>

                </main>
            </div>
        </div>
    </div>
</div>
