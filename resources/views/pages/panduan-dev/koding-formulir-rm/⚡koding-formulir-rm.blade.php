<?php

use Livewire\Component;

// Tutorial PENGKODEAN FORMULIR REKAM MEDIS — kode RM-KK.NN di cetakan PDF.
// Halaman referensi tanpa state form. Kode ditulis LANGSUNG di blade cetak
// (kode="RM-02.01 · Rev.0"); halaman ini memegang daftar kelompok & nama formulir,
// sedangkan view & revisi tiap kode dibaca dari blade — jadi tabelnya selalu
// sama dengan yang tercetak.
new class extends Component {
    // Tag komponen ditulis lewat token karena ComponentTagCompiler menyisir
    // SELURUH berkas SFC — literal tag di nowdoc blok kelas ikut dikompilasi.
    private const TAG_LAYOUT = '<' . 'x-pdf.layout-a4-with-out-background';
    private const TAG_LAYOUT_TUTUP = '</' . 'x-pdf.layout-a4-with-out-background>';

    public function kelompok(): array
    {
        return [
            '01' => 'Identitas dan Penjaminan',
            '02' => 'Persetujuan dan Penolakan',
            '03' => 'Asesmen',
            '04' => 'Catatan Terintegrasi dan Observasi',
            '05' => 'Pembedahan, Anestesi, dan Persalinan',
            '06' => 'Penunjang',
            '07' => 'Farmasi',
            '08' => 'Pemindahan Pasien',
            '09' => 'Ringkasan dan Keluar',
            '10' => 'Surat Keterangan',
            '11' => 'Surveilans PPI',
        ];
    }

    /**
     * Daftar Induk: kode → nama formulir. Formulir baru = tambah satu baris di sini
     * DAN pasang kode="…" di blade cetaknya. Kode tak pernah dihapus/dipakai ulang —
     * formulir dihentikan cukup diberi akhiran " (nonaktif)" pada namanya.
     */
    public function formulir(): array
    {
        return [
            'RM-01.01' => 'Identifikasi Bayi',
            'RM-01.02' => 'Formulir Penjaminan',

            'RM-02.01' => 'Persetujuan Umum (General Consent)',
            'RM-02.02' => 'Persetujuan/Penolakan Tindakan Kedokteran (Inform Consent)',
            'RM-02.03' => 'Penolakan Pemberian Obat',
            'RM-02.04' => 'Penolakan Resusitasi (DNR)',
            'RM-02.05' => 'Penundaan Pelayanan',
            'RM-02.06' => 'Pulang Atas Permintaan Sendiri (APS)',
            'RM-02.07' => 'Permintaan Pendapat Lain (Second Opinion)',
            'RM-02.08' => 'Permintaan Pelayanan Kerohanian',

            'RM-03.01' => 'Rekam Medis Rawat Jalan',
            'RM-03.02' => 'Rekam Medis Fisioterapi Rawat Jalan',
            'RM-03.03' => 'Rekam Medis Gawat Darurat',
            'RM-03.04' => 'Pengkajian Medis (Review Pengkajian Rawat Jalan)',
            'RM-03.05' => 'Pengkajian Awal Obstetri',
            'RM-03.06' => 'Pengkajian Awal Ginekologi',
            'RM-03.07' => 'Riwayat Obstetri',
            'RM-03.08' => 'Pengkajian Awal Bayi Baru Lahir',
            'RM-03.09' => 'Pengkajian Keperawatan Neonatal',
            'RM-03.10' => 'Pengkajian Pasien Akhir Hayat',

            'RM-04.01' => 'Catatan Perkembangan Pasien Terintegrasi (CPPT)',
            'RM-04.02' => 'Komunikasi SBAR',
            'RM-04.03' => 'Edukasi Pasien dan Keluarga Terintegrasi',
            'RM-04.04' => 'Observasi Persalinan',
            'RM-04.05' => 'Observasi Nifas',
            'RM-04.06' => 'Catatan Terapi Neonatal',
            'RM-04.07' => 'Formulir A Manajer Pelayanan Pasien (MPP)',
            'RM-04.08' => 'Formulir B Manajer Pelayanan Pasien (MPP)',

            'RM-05.01' => 'Pengkajian Pre Operasi',
            'RM-05.02' => 'Pengkajian Pra Anestesi dan Pra Sedasi',
            'RM-05.03' => 'Asesmen Pra Induksi',
            'RM-05.04' => 'Surgical Safety Checklist',
            'RM-05.05' => 'Laporan Operasi',
            'RM-05.06' => 'Laporan Anestesi',
            'RM-05.07' => 'Monitoring Pasca Anestesi',
            'RM-05.08' => 'Instruksi Pasca Bedah',
            'RM-05.09' => 'Laporan Persalinan',
            'RM-05.10' => 'Indikasi Sectio Caesarea',

            'RM-06.01' => 'Hasil Pemeriksaan Laboratorium',
            'RM-06.02' => 'Hasil Pemeriksaan Radiologi',
            'RM-06.03' => 'Permintaan Darah',

            'RM-07.01' => 'Resep Elektronik',
            'RM-07.02' => 'Resep Iterasi',
            'RM-07.03' => 'Riwayat Pengobatan',
            'RM-07.04' => 'Pelaporan Efek Samping Obat',

            'RM-08.01' => 'Transfer Pasien Gawat Darurat ke Rawat Inap',
            'RM-08.02' => 'Pemindahan Pasien Antar Ruang',

            'RM-09.01' => 'Resume Medis',
            'RM-09.02' => 'Ringkasan Pasien Pulang',
            'RM-09.03' => 'Profil Ringkas Medis Rawat Jalan (PRMRJ)',
            'RM-09.04' => 'Surat Keterangan Kematian',

            'RM-10.01' => 'Surat Keterangan Sakit',
            'RM-10.02' => 'Surat Keterangan Sehat',

            'RM-11.01' => 'Surveilans Hospital Acquired Pneumonia (HAP)',
            'RM-11.02' => 'Surveilans Ventilator Associated Pneumonia (VAP)',
            'RM-11.03' => 'Surveilans Infeksi Luka Operasi (ILO)',
            'RM-11.04' => 'Surveilans Infeksi Saluran Kemih (ISK)',
            'RM-11.05' => 'Surveilans Plebitis',
        ];
    }

    /**
     * Sisir semua *-print.blade.php dan baca atribut kode="…" di tag layout-nya.
     *
     * @return array{berkode: array<string, list<array{view: string, revisi: string}>>, takBaku: list<array{view: string, kode: string}>, tanpaKode: list<string>}
     */
    public function cetakan(): array
    {
        $direktoriViews = resource_path('views');
        $hasil = ['berkode' => [], 'takBaku' => [], 'tanpaKode' => []];

        $berkasList = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($direktoriViews, FilesystemIterator::SKIP_DOTS));
        foreach ($berkasList as $berkas) {
            $pathBerkas = $berkas->getPathname();
            if (!str_ends_with($pathBerkas, '-print.blade.php')) {
                continue;
            }
            $view = str_replace('/', '.', substr($pathBerkas, strlen($direktoriViews) + 1, -strlen('.blade.php')));

            if (!preg_match('/\bkode="([^"]*)"/', file_get_contents($pathBerkas), $cocokAtribut)) {
                $hasil['tanpaKode'][] = $view;
            } elseif (preg_match('/^(RM-\d{2}\.\d{2}) · Rev\.(\d+)$/u', $cocokAtribut[1], $cocokKode)) {
                $hasil['berkode'][$cocokKode[1]][] = ['view' => $view, 'revisi' => $cocokKode[2]];
            } else {
                $hasil['takBaku'][] = ['view' => $view, 'kode' => $cocokAtribut[1]];
            }
        }

        sort($hasil['tanpaKode']);

        return $hasil;
    }

    public function snippets(): array
    {
        $snippets = [

'anatomi' => <<<'TXT'
RM-05.06 · Rev.0
│  │  │      └─ revisi rancangan formulir, mulai 0
│  │  └─ nomor urut dalam kelompok (01, 02, …)
│  └─ kelompok isi rekam medis (01 – 11)
└─ awalan tetap "RM" — membedakan dari kode blanko
   down time (RJ-EMR-01, APT-01, DT-01)
TXT,

'blade-baru' => <<<'TXT'
# cetak-laporan-endoskopi-ri-print.blade.php

%TAG_LAYOUT% kode="RM-05.11 · Rev.0"
    title="LAPORAN TINDAKAN ENDOSKOPI — RAWAT INAP">

    ...isi formulir...

%TAG_LAYOUT_TUTUP%

# Tercetak persis apa adanya di pojok kanan atas:   RM-05.11 · Rev.0
# Layout lain yang juga punya prop kode: x-pdf.layout-a4, x-pdf.layout-kwitansi
TXT,

'daftar-baru' => <<<'TXT'
// resources/views/pages/panduan-dev/koding-formulir-rm/⚡koding-formulir-rm.blade.php
// → method formulir(). Taruh di kelompoknya; NN = nomor terakhir kelompok itu + 1.

'RM-05.10' => 'Indikasi Sectio Caesarea',
'RM-05.11' => 'Laporan Tindakan Endoskopi',      // ← baru

// Port ke jalur lain (RJ/UGD) TIDAK menambah baris — cukup pasang kode yang sama
// di blade cetak jalur itu; kolom "View cetak" di Daftar Formulir RM terisi sendiri.
TXT,

'kode-literal' => <<<'TXT'
BENAR   kode="RM-05.11 · Rev.0"
SALAH   kode="RM-05.11"                 ← revisi wajib ikut tertulis
SALAH   kode="RM-05.11 Rev.0"           ← pemisahnya " · " (spasi, titik tengah, spasi)
SALAH   :kode="$kodeFormulir"           ← tak terbaca Daftar Formulir RM
SALAH   title="RM-05.11 LAPORAN …"      ← kode diketik di judul, bukan prop kode

# Titik tengah "·" paling aman disalin dari blade cetak lain:
grep -rh 'kode="RM-' resources/views --include='*-print.blade.php' | head -1
TXT,

'revisi' => <<<'TXT'
# Rancangan General Consent berubah → SEMUA jalur naik bersamaan:
grep -rl 'kode="RM-02.01 · ' resources/views --include='*-print.blade.php'
#   …rj.general-consent.cetak-general-consent-rj-print
#   …ugd.general-consent.cetak-general-consent-print
#   …ri.general-consent.cetak-general-consent-ri-print

kode="RM-02.01 · Rev.0"   →   kode="RM-02.01 · Rev.1"

# TIDAK menaikkan revisi:
#   - perbaikan salah ketik, jarak, ukuran huruf, bug tampilan
#   - perubahan kode program yang tak mengubah apa yang diisi petugas
TXT,

'nonaktif' => <<<'TXT'
// Formulir tidak dipakai lagi — JANGAN hapus barisnya, JANGAN pakai ulang kodenya.
'RM-04.03' => 'Edukasi Pasien (nonaktif)',

// Rekam medis lama yang tercetak dengan RM-04.03 tetap bisa ditelusuri
// ke formulir yang benar. Formulir penggantinya mendapat kode BARU.
TXT,

'cek' => <<<'TXT'
# Semua kode yang terpasang, per blade
grep -rn 'kode="RM-' resources/views --include='*-print.blade.php'

# Blade cetak yang BELUM berkode
grep -rL 'kode="RM-' resources/views --include='*-print.blade.php'

# Satu kode dipakai di mana saja (mis. saat menaikkan revisi)
grep -rl 'kode="RM-02.01 · ' resources/views --include='*-print.blade.php'
TXT,

        ];

        return str_replace(
            ['%TAG_LAYOUT_TUTUP%', '%TAG_LAYOUT%'],
            [self::TAG_LAYOUT_TUTUP, self::TAG_LAYOUT],
            $snippets,
        );
    }
};
?>

<div>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @php
        $snippet = $this->snippets();
        $kelompok = $this->kelompok();
        $formulir = $this->formulir();
        $cetakan = $this->cetakan();

        $menuGroups = [
            'Mulai' => [
                'pendahuluan' => 'Pendahuluan',
                'aturan'      => 'Aturan Kode',
            ],
            'Daftar Induk' => [
                'daftar'      => 'Daftar Formulir RM',
                'pengecualian' => 'Cetakan Tanpa Kode',
            ],
            'Praktik' => [
                'formulir-baru' => 'Menambah Formulir Baru',
                'revisi'        => 'Revisi & Nonaktif',
                'pemeriksa'     => 'Cek & Checklist',
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
                    <span class="ds-body-sm" style="color:var(--muted-soft)">/ Pengkodean Formulir RM</span>
                </div>
                <x-theme-toggle />
            </div>

            <div class="mt-8 grid grid-cols-1 gap-10 lg:grid-cols-[240px_1fr]">

                {{-- ============ SIDEBAR ============ --}}
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
                            Kode: prop <span class="ds-code">kode</span> di blade cetak<br>
                            Nama formulir: halaman ini
                        </div>
                    </div>
                </aside>

                {{-- ============ KONTEN ============ --}}
                <main style="min-width:0">

                    @include('pages.panduan-dev.koding-formulir-rm.koding-formulir-rm-mulai')

                    @include('pages.panduan-dev.koding-formulir-rm.koding-formulir-rm-daftar')

                    @include('pages.panduan-dev.koding-formulir-rm.koding-formulir-rm-praktik')

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
