<?php
/**
 * Pemeriksa tampilan baku modul dokumen (docs/modul-dokumen-ri-pattern.md §2a).
 *
 * Jalankan dari akar repo:
 *   php .claude/skills/modul-dokumen/periksa-tampilan.php            # semua modul dokumen
 *   php .claude/skills/modul-dokumen/periksa-tampilan.php <berkas…>  # sebagian saja
 *
 * Memeriksa HTML HASIL RENDER, bukan isi berkas — itu bedanya dengan grep:
 *   1. keseimbangan tag di KEDUA layar (daftar & formulir)
 *   2. tombol tutup benar-benar mepet kanan (dibaca lewat DOM: induk & saudaranya)
 *   3. layar daftar punya tombol Tutup + Isi Formulir Baru (modul dua layar)
 *   4. saat kosong, tabel tetap tampil dengan keterangan
 */
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
auth()->loginUsingId(1);

function namaKomponen(string $p): string {
    $r = preg_replace('/\.blade\.php$/', '', str_replace('resources/views/pages/', '', $p));
    return 'pages::' . str_replace('/', '.', str_replace('⚡', '', $r));
}
function saldoTag(string $h): array {
    $out = [];
    foreach (['div','section','fieldset','table','ul','ol','p','span'] as $t) {
        $b = preg_match_all('/<'.$t.'[\s>]/i', $h);
        $c = preg_match_all('#</'.$t.'>#i', $h);
        if ($b !== $c) $out[$t] = $b - $c;
    }
    return $out;
}
function tutupMepetKanan(string $h): ?string {
    $doc = new DOMDocument(); libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $h . '</div>'); libxml_clear_errors();
    $xp = new DOMXPath($doc);
    $btn = $xp->query('//button[@*[name()="wire:click"]="closeModal"]')->item(0);
    if (!$btn) return 'tombol tutup tak ada di DOM';
    $par = $btn->parentNode;
    $prev = $btn->previousSibling; while ($prev && $prev->nodeType !== XML_ELEMENT_NODE) $prev = $prev->previousSibling;
    $kPar = $par->getAttribute('class'); $kBtn = $btn->getAttribute('class');
    $kPrev = $prev ? $prev->getAttribute('class') : '';
    if (!str_contains($kPar, 'flex')) return 'tombol tutup bukan anak baris flex';
    $mepet = str_contains($kPar, 'justify-between') || str_contains($kBtn, 'ml-auto') || str_contains($kPrev, 'flex-1');
    return $mepet ? null : 'tombol tutup tak terdorong ke kanan';
}

$berkas = array_slice($argv, 1);
if (!$berkas) $berkas = glob('resources/views/pages/transaksi/*/emr-*/modul-dokumen/*/⚡rm-*-actions.blade.php');
$berkas = array_values(array_filter($berkas, fn($f) => str_contains(file_get_contents($f), '<x-modal')));

$masalah = 0;
foreach ($berkas as $path) {
    $catatan = [];
    $terender = false;
    foreach ([[], ['riHdrNo' => null], ['rjNo' => null]] as $args) {
        try { $t = Livewire\Livewire::test(namaKomponen($path), $args); } catch (\Throwable $e) { continue; }
        $terender = true;
        $daftar = $t->html();
        if ($s = saldoTag($daftar)) $catatan[] = 'tag layar daftar timpang: ' . json_encode($s);
        if ($e = tutupMepetKanan($daftar)) $catatan[] = $e;

        $duaLayar = str_contains(file_get_contents($path), 'this->diForm()');
        if ($duaLayar) {
            if (!preg_match('/>\s*Tutup\s*</', $daftar)) $catatan[] = 'layar daftar tanpa tombol Tutup';
            if (!str_contains($daftar, 'wire:click="tambahEntri"')) $catatan[] = 'layar daftar tanpa Isi Formulir Baru';
            if (str_contains($daftar, '<table') && !str_contains($daftar, 'Belum ada data'))
                $catatan[] = 'tabel tanpa keterangan saat kosong';
            try {
                $t->call('tambahEntri');
                $form = $t->html();
                if ($s = saldoTag($form)) $catatan[] = 'tag layar formulir timpang: ' . json_encode($s);
                if (!str_contains($form, 'wire:click="kembaliKeDaftar"')) $catatan[] = 'layar formulir tanpa Kembali ke Daftar';
            } catch (\Throwable $e) { $catatan[] = 'tambahEntri gagal: ' . substr($e->getMessage(), 0, 60); }
        }
        break;
    }
    if (!$terender) $catatan[] = 'gagal dirender';
    if ($catatan) { $masalah++; printf("✗ %-46s %s\n", substr(basename($path), 0, 46), implode(' | ', $catatan)); }
}
printf("\nDIPERIKSA %d modul | BERMASALAH %d\n", count($berkas), $masalah);
exit($masalah > 0 ? 1 : 0);
