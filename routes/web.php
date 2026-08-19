<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;



Route::livewire('/', 'welcome')->name('home');

// Display publik (TV antrian) — tanpa auth supaya bisa dibuka di layar tunggu pasien.
Route::livewire('/display/antrian-apotek-rj', 'pages::display.antrian-apotek-rj.antrian-apotek-rj')
    ->name('display.antrian-apotek-rj');
Route::livewire('/display/antrian-apotek-ugd', 'pages::display.antrian-apotek-ugd.antrian-apotek-ugd')
    ->name('display.antrian-apotek-ugd');
Route::livewire('/display/antrian-apotek-ri', 'pages::display.antrian-apotek-ri.antrian-apotek-ri')
    ->name('display.antrian-apotek-ri');
Route::livewire('/display/jadwal-poli', 'pages::display.jadwal-poli.jadwal-poli')
    ->name('display.jadwal-poli');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
});
// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::livewire('/master/poli', 'pages::master.poli.index')
//         ->name('master.poli');
// });


Route::middleware(['auth'])->group(function () {
    // Halaman acuan standarisasi UI (design system internal)
    Route::livewire('/panduan-dev', 'pages::panduan-dev.panduan-dev')
        ->name('panduan-dev');

    // Tutorial standarisasi koding modul master (docs/standar-master-module.md versi web)
    Route::livewire('/panduan-dev/koding-master', 'pages::panduan-dev.koding-master.koding-master')
        ->name('panduan-dev.koding-master');

    // Tutorial standarisasi koding modul transaksi (RJ/UGD/RI: pendaftaran-pelayanan-kasir + EMR/dokumen/administrasi)
    Route::livewire('/panduan-dev/koding-transaksi', 'pages::panduan-dev.koding-transaksi.koding-transaksi')
        ->name('panduan-dev.koding-transaksi');

    // Tutorial standarisasi pengiriman data SATUSEHAT (FHIR R4: auth, transport, IHS,
    // urutan resource, standarisasi per-resource, peta dashboard) — docs/satusehat-api.md versi web
    Route::livewire('/panduan-dev/koding-satusehat', 'pages::panduan-dev.koding-satusehat.koding-satusehat')
        ->name('panduan-dev.koding-satusehat');

    // Tutorial konsep administrasi/kasir RJ/UGD/RI sampai pulang + transfer & model batal
    Route::livewire('/panduan-dev/koding-administrasi', 'pages::panduan-dev.koding-administrasi.koding-administrasi')
        ->name('panduan-dev.koding-administrasi');

    // Tutorial standar struktur folder & penamaan berkas (app/ + resources/views/):
    // prefix ⚡, suffix peran & jalur, batas Trait vs Support, prefix URL, 7 pemeriksa
    // — docs/standar-struktur-folder.md versi web
    Route::livewire('/panduan-dev/koding-struktur', 'pages::panduan-dev.koding-struktur.koding-struktur')
        ->name('panduan-dev.koding-struktur');

    // Katalog skill repo (.claude/skills/*) — versi web dari docs/skills-index.md
    Route::livewire('/panduan-dev/koding-skill', 'pages::panduan-dev.koding-skill.koding-skill')
        ->name('panduan-dev.koding-skill');

    // Tutorial alur pelayanan pasien (pendaftaran → EMR → apotek → kasir) per jalur RJ/UGD/RI
    Route::livewire('/panduan-dev/alur-pelayanan', 'pages::panduan-dev.alur-pelayanan.alur-pelayanan')
        ->name('panduan-dev.alur-pelayanan');

    // Dokumentasi Approval Hub — arsitektur, alur kerja, referensi teknis casemix/E-Klaim/SATUSEHAT
    Route::livewire('/panduan-dev/approval-hub', 'pages::panduan-dev.approval-hub.approval-hub')
        ->name('panduan-dev.approval-hub');

    // ===========================================
    // DOWN TIME — FORMULIR MANUAL WAKTU HENTI SIMRS
    // ===========================================
    // Katalog formulir manual (EMR RJ, Administrasi RJ, Kasir RJ, Apotek RJ, Umum/IT)
    // + unduh PDF per formulir / bundel per area untuk sosialisasi.
    Route::livewire('/downtime/formulir-manual', 'pages::downtime.formulir-manual.formulir-manual')
        ->name('downtime.formulir-manual');

    // Daftar tarif (price list) acuan pengisian nominal di formulir manual —
    // kamar, visite/konsul, jasa medis & dokter, penunjang, obat, lain-lain.
    Route::livewire('/downtime/daftar-tarif', 'pages::downtime.daftar-tarif.daftar-tarif')
        ->name('downtime.daftar-tarif');

    Route::livewire('/master/poli', 'pages::master.master-poli.master-poli')
        ->name('master.poli');

    Route::livewire('/master/karyawan', 'pages::master.master-karyawan.master-karyawan')
        ->name('master.karyawan');

    // ===========================================
    // MASTER - SETUP JADWAL PELAYANAN DOKTER BPJS
    // ===========================================
    Route::livewire('/master/setup-jadwal-bpjs', 'pages::master.setup-jadwal-bpjs.setup-jadwal-bpjs')
        ->name('master.setup-jadwal-bpjs');

    Route::livewire('/master/dokter', 'pages::master.master-dokter.master-dokter')
        ->name('master.dokter');

    Route::livewire('/master/pasien', 'pages::master.master-pasien.master-pasien')
        ->name('master.pasien');

    Route::livewire('/master/obat', 'pages::master.master-obat.master-obat')
        ->name('master.obat');

    Route::livewire('/master/obat-kronis', 'pages::master.master-obat-kronis.master-obat-kronis')
        ->name('master.obat-kronis');

    Route::livewire('/master/signa-catatan', 'pages::master.master-signa-catatan.master-signa-catatan')
        ->name('master.signa-catatan');

    Route::livewire('/master/interaksi-obat', 'pages::master.master-interaksi-obat.interaksi-obat-hdr.master-interaksi-obat-hdr')
        ->name('master.interaksi-obat');

    Route::livewire('/master/stocklocations', 'pages::master.master-stocklocations.master-stocklocations')
        ->name('master.stocklocations');

    Route::livewire('/master/diagnosa', 'pages::master.master-diagnosa.master-diagnosa')
        ->name('master.diagnosa');

    Route::livewire('/master/kamar', 'pages::master.master-kamar.bangsal.master-bangsal')
        ->name('master.kamar');

    Route::livewire('/master/laborat', 'pages::master.master-laborat.clab.master-clab')
        ->name('master.laborat');

    Route::livewire('/master/kelas', 'pages::master.master-kelas-rawat.master-kelas-rawat')
        ->name('master.kelas');

    Route::livewire('/master/agama', 'pages::master.master-agama.master-agama')
        ->name('master.agama');

    Route::livewire('/master/others', 'pages::master.master-others.master-others')
        ->name('master.others');

    Route::livewire('/master/jasa-medis', 'pages::master.master-jasa-medis.jasa-medis.master-jasa-medis')
        ->name('master.jasa-medis');

    Route::livewire('/master/jasa-dokter', 'pages::master.master-jasa-dokter.jasa-dokter.master-jasa-dokter')
        ->name('master.jasa-dokter');

    Route::livewire('/master/radiologis', 'pages::master.master-radiologis.master-radiologis')
        ->name('master.radiologis');

    Route::livewire('/master/diag-keperawatan', 'pages::master.master-diag-keperawatan.master-diag-keperawatan')
        ->name('master.diag-keperawatan');

    // ===========================================
    // MASTER IDENTITAS RS (kop cetakan + setelan transaksi)
    // ===========================================
    Route::livewire('/master/identitas', 'pages::master.master-identitas.master-identitas')
        ->name('master.identitas');

    // ===========================================
    // MASTER TERMINOLOGI SATUSEHAT (LOINC & SNOMED CT)
    // ===========================================
    Route::livewire('/master/loinc', 'pages::master.master-loinc.master-loinc')
        ->name('master.loinc');

    Route::livewire('/master/snomed', 'pages::master.master-snomed.master-snomed')
        ->name('master.snomed');

    // ===========================================
    // RAWAT JALAN (RJ) - DAFTAR RAWAT JALAN (Pendaftaran)
    // ===========================================
    Route::livewire('/rj/daftar', 'pages::transaksi.rj.daftar-rj.daftar-rj')
        ->name('rj.daftar');

    // Jadwal Kontrol Pasien (SKDP RJ+RI) — pendaftaran geser tanggal kontrol + update BPJS
    Route::livewire('/kontrol/jadwal-kontrol', 'pages::transaksi.kontrol.jadwal-kontrol.jadwal-kontrol')
        ->name('kontrol.jadwal-kontrol');

    // ===========================================
    // RAWAT JALAN (RJ) - PELAYANAN POLI (Dokter/Perawat)
    // ===========================================
    Route::livewire('/rj/pelayanan', 'pages::transaksi.rj.pelayanan-rj.pelayanan-rj')
        ->name('rj.pelayanan');

    // ===========================================
    // RAWAT JALAN (RJ) - DAFTAR PASIEN BULANAN
    // ===========================================
    Route::livewire('/rj/daftar-bulanan', 'pages::transaksi.rj.daftar-rj-bulanan.daftar-rj-bulanan')
        ->name('rj.daftar-bulanan');

    // ===========================================
    // FILES — Serve private file (auth required)
    // ===========================================
    // Arsitektur file storage:
    //   1. Laravel WRITE upload ke 'upload/...' (storage lokal sementara)
    //   2. External program sync 'upload/...' → '\\fileserver\share' (offload file besar)
    //   3. SMB share di-mount ke 'mount/...' (read-only dari Laravel)
    //   4. View Lihat: baca dari 'mount/...' (utama), fallback 'upload/...' bila
    //      program sync belum jalan / file masih di cache lokal.
    //
    // Path bisa nested (mis. mount/penunjang/radiologi/foto/xxx.pdf) — catch-all {path}.
    // Whitelist = prefix yang diizinkan untuk akses publik via route ini.
    Route::get('/files/{path}', function (string $path) {
        // Whitelist pakai 'mount/...' sebagai canonical (sumber data terbaru di share).
        $allowedPrefixes = [
            'mount/bpjs',                       // Berkas BPJS (SEP/grouping/RM/SKDP/lain-lain)
            'mount/penunjang/radiologi',        // Foto + hasil bacaan radiologi (1 folder)
            'mount/penunjang/lab-luar',         // Hasil lab luar (PDF/JPG)
            'mount/penunjang/emr/uploadHasilPenunjang', // Hasil penunjang dari EMR RJ/UGD/RI
        ];

        $matched = null;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix . '/')) {
                $matched = $prefix;
                break;
            }
        }
        if ($matched === null) {
            abort(403, 'Path tidak diizinkan.');
        }

        $filename = basename($path);
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            abort(403, 'Nama file tidak valid.');
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        // Coba baca dari mount/... (canonical, file di share). Kalau tidak ada,
        // fallback ke upload/... (cache lokal — file belum di-sync external program).
        $mountPath = $matched . '/' . $filename;
        $uploadPath = preg_replace('#^mount/#', 'upload/', $matched, 1) . '/' . $filename;

        if ($disk->exists($mountPath)) {
            return $disk->response($mountPath);
        }
        if ($disk->exists($uploadPath)) {
            return $disk->response($uploadPath);
        }
        abort(404, 'Berkas tidak ditemukan.');
    })->name('files.show')->where('path', '[A-Za-z0-9._\-/]+');

    // ===========================================
    // RAWAT JALAN (RJ) - BOOKING RJ (Mobile JKN)
    // ===========================================
    Route::livewire('/rj/booking', 'pages::transaksi.rj.booking-rj.booking-rj')
        ->name('rj.booking');

    // ===========================================
    // TRANSAKSI RJ - ANTRIAN APOTEK
    // ===========================================
    Route::livewire('/rj/antrian-apotek', 'pages::transaksi.rj.antrian-apotek-rj.antrian-apotek-rj')
        ->name('rj.antrian-apotek');

    // ===========================================
    // TRANSAKSI RJ - ANTRIAN KASIR (clone Apotek RJ)
    // ===========================================
    Route::livewire('/rj/antrian-kasir', 'pages::transaksi.rj.antrian-kasir-rj.antrian-kasir-rj')
        ->name('rj.antrian-kasir');


    // ===========================================
    // UGD - DAFTAR UGD (Pendaftaran)
    // ===========================================
    Route::livewire('/ugd/daftar', 'pages::transaksi.ugd.daftar-ugd.daftar-ugd')
        ->name('ugd.daftar');

    // ===========================================
    // UGD - PELAYANAN UGD (Dokter/Perawat — EMR)
    // ===========================================
    Route::livewire('/ugd/pelayanan', 'pages::transaksi.ugd.pelayanan-ugd.pelayanan-ugd')
        ->name('ugd.pelayanan');

    Route::livewire('/ugd/daftar-bulanan', 'pages::transaksi.ugd.daftar-ugd-bulanan.daftar-ugd-bulanan')
        ->name('ugd.daftar-bulanan');


    // ===========================================
    // TRANSAKSI UGD - ANTRIAN APOTEK
    // ===========================================
    Route::livewire('/ugd/antrian-apotek', 'pages::transaksi.ugd.antrian-apotek-ugd.antrian-apotek-ugd')
        ->name('ugd.antrian-apotek');

    // ===========================================
    // TRANSAKSI UGD - ANTRIAN KASIR (clone Apotek UGD)
    // ===========================================
    Route::livewire('/ugd/antrian-kasir', 'pages::transaksi.ugd.antrian-kasir-ugd.antrian-kasir-ugd')
        ->name('ugd.antrian-kasir');


    // ===========================================
    // TRANSAKSI APOTEK - GABUNGAN RJ + UGD + RI (tab)
    // ===========================================
    Route::livewire('/apotek', 'pages::transaksi.apotek.apotek')
        ->name('apotek');

    // ===========================================
    // TRANSAKSI KASIR - GABUNGAN RJ + UGD + RI (tab) — clone Apotek
    // ===========================================
    Route::livewire('/kasir', 'pages::transaksi.kasir.kasir')
        ->name('kasir');

    // ===========================================
    // TRANSAKSI CASEMIX - GABUNGAN Bulanan RJ + UGD + RI (tab)
    // ===========================================
    Route::livewire('/casemix', 'pages::transaksi.casemix.casemix')
        ->name('casemix');

    // ===========================================
    // APPROVAL HUB — Review & approve data AI sebelum kirim ke sistem eksternal
    // ===========================================
    Route::livewire('/approval-hub', 'pages::transaksi.approval-hub.approval-hub')
        ->name('approval-hub');

    // Direct route — Antrian Apotek RI (tanpa wrapper tab)
    Route::livewire('/ri-resep/antrian', 'pages::transaksi.ri-resep.antrian-ri-resep.antrian-ri-resep')
        ->name('ri-resep.antrian');

    // PTO — Pemantauan Terapi Obat (program apoteker, baca e-resep RI)
    Route::livewire('/ri/pto', 'pages::transaksi.ri.pto.pto')
        ->name('ri.pto');

    // Direct route — Antrian Kasir RI (clone Apotek RI)
    Route::livewire('/kasir/antrian-ri', 'pages::transaksi.kasir.antrian-kasir-ri.antrian-kasir-ri')
        ->name('kasir.antrian-ri');

    // Direct route — Daftar Pasien RI Kasir (per rihdr, action: Administrasi saja)
    Route::livewire('/kasir/daftar-ri', 'pages::transaksi.kasir.daftar-kasir-ri.daftar-kasir-ri')
        ->name('kasir.daftar-ri');


    // ===========================================
    // RI - DAFTAR RI
    // ===========================================
    Route::livewire('/ri/daftar', 'pages::transaksi.ri.daftar-ri.daftar-ri')
        ->name('ri.daftar');

    Route::livewire('/ri/daftar-bulanan', 'pages::transaksi.ri.daftar-ri-bulanan.daftar-ri-bulanan')
        ->name('ri.daftar-bulanan');

    // Gizi Rawat Inap — worklist unit gizi (program diet harian + rekap porsi)
    Route::livewire('/ri/gizi', 'pages::transaksi.ri.gizi-ri.gizi-ri')
        ->name('ri.gizi');

    // ===========================================
    // RI — UPDATE TEMPAT TIDUR (Aplicares + SIRS)
    // ===========================================
    Route::livewire('/ri/update-tt', 'pages::transaksi.ri.update-tt-ri.update-tt-ri')
        ->name('ri.update-tt');
    // ===========================================
    // OPERASI - JADWAL OPERASI
    // ===========================================
    Route::livewire('/operasi/jadwal-operasi', 'pages::operasi.jadwal-operasi.jadwal-operasi')
        ->name('operasi.jadwal-operasi');

    // ===========================================
    // KEUANGAN - PENERIMAAN KAS TU
    // ===========================================
    Route::livewire('/keuangan/penerimaan-kas-tu', 'pages::transaksi.keuangan.penerimaan-kas-tu.penerimaan-kas-tu')
        ->name('keuangan.penerimaan-kas-tu');

    // ===========================================
    // KEUANGAN - PENGELUARAN KAS TU
    // ===========================================
    Route::livewire('/keuangan/pengeluaran-kas-tu', 'pages::transaksi.keuangan.pengeluaran-kas-tu.pengeluaran-kas-tu')
        ->name('keuangan.pengeluaran-kas-tu');

    // ===========================================
    // KEUANGAN - ANALISA PERPUTARAN OBAT (fast/slow/dead moving)
    // ===========================================
    Route::livewire('/keuangan/analisa-perputaran-obat', 'pages::transaksi.keuangan.analisa-perputaran-obat.analisa-perputaran-obat')
        ->name('keuangan.analisa-perputaran-obat');

    // ===========================================
    // KEUANGAN - PIUTANG PASIEN (2 konsep, 2 komponen)
    //   1. Klaim BPJS  : bundel per bulan, tab RJ/UGD/RI
    //   2. Per pasien  : umumnya pasien umum, alokasi FIFO
    // ===========================================
    Route::livewire('/keuangan/pembayaran-piutang-bpjs', 'pages::transaksi.keuangan.pembayaran-piutang.pembayaran-piutang-bpjs')
        ->name('keuangan.pembayaran-piutang-bpjs');

    Route::livewire('/keuangan/pembayaran-piutang-pasien', 'pages::transaksi.keuangan.pembayaran-piutang.pembayaran-piutang-pasien')
        ->name('keuangan.pembayaran-piutang-pasien');

    // ===========================================
    // KEUANGAN - PEMBAYARAN HUTANG PBF (medis)
    // ===========================================
    Route::livewire('/keuangan/pembayaran-hutang-pbf', 'pages::transaksi.keuangan.pembayaran-hutang-pbf.pembayaran-hutang-pbf')
        ->name('keuangan.pembayaran-hutang-pbf');

    // ===========================================
    // KEUANGAN - PEMBAYARAN HUTANG NON-MEDIS
    // ===========================================
    Route::livewire('/keuangan/pembayaran-hutang-non-medis', 'pages::transaksi.keuangan.pembayaran-hutang-non-medis.pembayaran-hutang-non-medis')
        ->name('keuangan.pembayaran-hutang-non-medis');

    // ===========================================
    // KEUANGAN - TOPUP SUPPLIER PBF (medis)
    // ===========================================
    Route::livewire('/keuangan/topup-supplier-pbf', 'pages::transaksi.keuangan.topup-supplier-pbf.topup-supplier-pbf')
        ->name('keuangan.topup-supplier-pbf');

    // ===========================================
    // KEUANGAN - TOPUP SUPPLIER NON-MEDIS
    // ===========================================
    Route::livewire('/keuangan/topup-supplier-non-medis', 'pages::transaksi.keuangan.topup-supplier-non-medis.topup-supplier-non-medis')
        ->name('keuangan.topup-supplier-non-medis');

    // ===========================================
    // KEUANGAN - SALDO KAS
    // ===========================================
    Route::livewire('/keuangan/saldo-kas', 'pages::transaksi.keuangan.saldo-kas.saldo-kas')
        ->name('keuangan.saldo-kas');

    // ===========================================
    // KEUANGAN - BUKU BESAR
    // ===========================================
    Route::livewire('/keuangan/buku-besar', 'pages::transaksi.keuangan.buku-besar.buku-besar')
        ->name('keuangan.buku-besar');

    // ===========================================
    // KEUANGAN - LAPORAN LABA RUGI
    // ===========================================
    Route::livewire('/keuangan/laba-rugi', 'pages::transaksi.keuangan.laba-rugi.laba-rugi')
        ->name('keuangan.laba-rugi');

    // ===========================================
    // KEUANGAN - LAPORAN NERACA
    // ===========================================
    Route::livewire('/keuangan/neraca', 'pages::transaksi.keuangan.neraca.neraca')
        ->name('keuangan.neraca');

    // ===========================================
    // MASTER AKUNTANSI - GROUP AKUN
    // ===========================================
    Route::livewire('/master/group-akun', 'pages::master.master-akuntansi.master-group-akun.master-group-akun')
        ->name('master.group-akun');

    // ===========================================
    // MASTER AKUNTANSI - AKUN
    // ===========================================
    Route::livewire('/master/akun', 'pages::master.master-akuntansi.master-akun.master-akun')
        ->name('master.akun');

    // ===========================================
    // MASTER AKUNTANSI - KONFIGURASI AKUN TRANSAKSI
    // ===========================================
    Route::livewire('/master/konf-akun-trans', 'pages::master.master-akuntansi.master-konf-akun-trans.master-konf-akun-trans')
        ->name('master.konf-akun-trans');

    // ===========================================
    // GUDANG - PENERIMAAN MEDIS
    // ===========================================
    Route::livewire('/gudang/penerimaan-medis', 'pages::transaksi.gudang.penerimaan-medis.penerimaan-medis')
        ->name('gudang.penerimaan-medis');

    Route::livewire('/gudang/transfer-stock', 'pages::transaksi.gudang.transfer-stock.transfer-stock')
        ->name('gudang.transfer-stock');

    Route::livewire('/gudang/transfer-stock-non', 'pages::transaksi.gudang.transfer-stock-non.transfer-stock-non')
        ->name('gudang.transfer-stock-non');

    // ===========================================
    // GUDANG - PENERIMAAN NON-MEDIS
    // ===========================================
    Route::livewire('/gudang/penerimaan-non-medis', 'pages::transaksi.gudang.penerimaan-non-medis.penerimaan-non-medis')
        ->name('gudang.penerimaan-non-medis');

    // ===========================================
    // GUDANG - KARTU STOCK GUDANG (warehouse)
    // ===========================================
    Route::livewire('/gudang/kartu-stock', 'pages::transaksi.gudang.kartu-stock.kartu-stock')
        ->name('gudang.kartu-stock');

    // ===========================================
    // GUDANG - KARTU STOCK APOTEK
    // ===========================================
    Route::livewire('/gudang/kartu-stock-apt', 'pages::transaksi.gudang.kartu-stock-apt.kartu-stock-apt')
        ->name('gudang.kartu-stock-apt');

    // ===========================================
    // GUDANG - KARTU STOCK NON-MEDIS
    // ===========================================
    Route::livewire('/gudang/kartu-stock-non', 'pages::transaksi.gudang.kartu-stock-non.kartu-stock-non')
        ->name('gudang.kartu-stock-non');

    // ===========================================
    // TRANSAKSI PENUNJANG - LABORATORIUM
    // ===========================================
    Route::livewire('/penunjang/laborat', 'pages::transaksi.penunjang.laborat.daftar-laborat')
        ->name('penunjang.laborat');

    Route::livewire('/penunjang/laborat/lab-luar', 'pages::transaksi.penunjang.laborat.lab-luar.lab-luar')
        ->name('penunjang.laborat.lab-luar');

    Route::livewire('/penunjang/radiologi/upload', 'pages::transaksi.penunjang.radiologi.upload-radiologi')
        ->name('penunjang.radiologi.upload');

    // ===========================================
    // TRANSAKSI PENUNJANG - KAMAR OPERASI
    // ===========================================
    Route::livewire('/penunjang/kamar-operasi', 'pages::transaksi.penunjang.kamar-operasi.daftar-kamar-operasi')
        ->name('penunjang.kamar-operasi');

    // ===========================================
    // DATABASE MONITOR - MONITORING DASHBOARD
    // ===========================================
    Route::livewire('/database-monitor/monitoring-dashboard', 'pages::database-monitor.monitoring-dashboard.monitoring-dashboard')
        ->name('database-monitor.monitoring-dashboard');

    // ===========================================
    // DATABASE MONITOR - MONITORING MOUNT CONTROL
    // ===========================================
    Route::livewire('/database-monitor/monitoring-mount-control', 'pages::database-monitor.monitoring-mount-control.monitoring-mount-control')
        ->name('database-monitor.monitoring-mount-control');

    // ===========================================
    // DATABASE MONITOR - USER CONTROL
    // ===========================================
    Route::livewire('/database-monitor/user-control', 'pages::database-monitor.user-control.user-control')
        ->name('database-monitor.user-control');

    // ===========================================
    // DATABASE MONITOR - USER ONLINE
    // ===========================================
    Route::livewire('/database-monitor/user-online', 'pages::database-monitor.user-online.user-online')
        ->name('database-monitor.user-online');

    // ===========================================
    // DATABASE MONITOR - ROLE CONTROL
    // ===========================================
    Route::livewire('/database-monitor/role-control', 'pages::database-monitor.role-control.role-control')
        ->name('database-monitor.role-control');

    // ===========================================
    // DASHBOARD MANAJEMEN
    // ===========================================
    Route::livewire('/manajemen/indikator-pelayanan', 'pages::manajemen.indikator-pelayanan.indikator-pelayanan')
        ->name('manajemen.indikator-pelayanan');

    Route::livewire('/manajemen/indikator-penunjang', 'pages::manajemen.indikator-penunjang.indikator-penunjang')
        ->name('manajemen.indikator-penunjang');

    Route::livewire('/manajemen/indikator-tu', 'pages::manajemen.indikator-tu.indikator-tu')
        ->name('manajemen.indikator-tu');

    Route::livewire('/manajemen/monitoring-keuangan', 'pages::manajemen.monitoring-keuangan.monitoring-keuangan')
        ->name('manajemen.monitoring-keuangan');

    Route::livewire('/manajemen/rs/satu-sehat/monitoring-satu-sehat', 'pages::manajemen.rs.satu-sehat.monitoring-satu-sehat.monitoring-satu-sehat')
        ->name('manajemen.rs.satu-sehat.monitoring-satu-sehat');

    Route::livewire('/manajemen/rs/vclaim/laporan-rujukan-keluar', 'pages::manajemen.rs.vclaim.laporan-rujukan-keluar.laporan-rujukan-keluar')
        ->name('manajemen.rs.vclaim.laporan-rujukan-keluar');

    Route::livewire('/manajemen/rs/vclaim/laporan-rujukan-masuk', 'pages::manajemen.rs.vclaim.laporan-rujukan-masuk.laporan-rujukan-masuk')
        ->name('manajemen.rs.vclaim.laporan-rujukan-masuk');

    Route::livewire('/manajemen/laporan-diagnosa', 'pages::manajemen.laporan-diagnosa.laporan-diagnosa')
        ->name('manajemen.laporan-diagnosa');

    Route::livewire('/database-monitor/log-bpjs', 'pages::database-monitor.log-bpjs.log-bpjs')
        ->name('database-monitor.log-bpjs');

    Route::livewire('/manajemen/rs/tu/pendapatan-jasa-dokter', 'pages::manajemen.rs.tu.pendapatan-jasa-dokter.pendapatan-jasa-dokter')
        ->name('manajemen.rs.tu.pendapatan-jasa-dokter');

    Route::livewire('/manajemen/rs/tu/pendapatan-jasa-medis', 'pages::manajemen.rs.tu.pendapatan-jasa-medis.pendapatan-jasa-medis')
        ->name('manajemen.rs.tu.pendapatan-jasa-medis');

    Route::livewire('/manajemen/rs/tu/pendapatan-jasa-karyawan', 'pages::manajemen.rs.tu.pendapatan-jasa-karyawan.pendapatan-jasa-karyawan')
        ->name('manajemen.rs.tu.pendapatan-jasa-karyawan');

    Route::livewire('/manajemen/rs/tu/pendapatan-rs', 'pages::manajemen.rs.tu.pendapatan-rs.pendapatan-rs')
        ->name('manajemen.rs.tu.pendapatan-rs');

    Route::livewire('/manajemen/rs/tu/piutang-pasien', 'pages::manajemen.rs.tu.piutang-pasien.piutang-pasien')
        ->name('manajemen.rs.tu.piutang-pasien');

    Route::livewire('/manajemen/rs/tu/gaji-dokter', 'pages::manajemen.rs.tu.gaji-dokter.gaji-dokter')
        ->name('manajemen.rs.tu.gaji-dokter');

    Route::livewire('/manajemen/ai-chat', 'pages::manajemen.ai-chat.ai-chat')
        ->name('manajemen.ai-chat');

    Route::livewire('/manajemen/mutasi-obat', 'pages::manajemen.mutasi-obat.mutasi-obat')
        ->name('manajemen.mutasi-obat');

    Route::livewire('/manajemen/transfer-antar-ruangan', 'pages::manajemen.transfer-antar-ruangan.transfer-antar-ruangan')
        ->name('manajemen.transfer-antar-ruangan');

    Route::livewire('/manajemen/rs/rj/laporan-task-id-rj', 'pages::manajemen.rs.rj.laporan-task-id-rj.laporan-task-id-rj')
        ->name('manajemen.rs.rj.laporan-task-id-rj');

    Route::livewire('/manajemen/rs/ugd/laporan-task-id-ugd', 'pages::manajemen.rs.ugd.laporan-task-id-ugd.laporan-task-id-ugd')
        ->name('manajemen.rs.ugd.laporan-task-id-ugd');

    Route::livewire('/manajemen/rs/rj/laporan-kunjungan-rj', 'pages::manajemen.rs.rj.laporan-kunjungan-rj.laporan-kunjungan-rj')
        ->name('manajemen.rs.rj.laporan-kunjungan-rj');

    Route::livewire('/manajemen/rs/ugd/laporan-kunjungan-ugd', 'pages::manajemen.rs.ugd.laporan-kunjungan-ugd.laporan-kunjungan-ugd')
        ->name('manajemen.rs.ugd.laporan-kunjungan-ugd');

    Route::livewire('/manajemen/rs/ri/laporan-kunjungan-ri', 'pages::manajemen.rs.ri.laporan-kunjungan-ri.laporan-kunjungan-ri')
        ->name('manajemen.rs.ri.laporan-kunjungan-ri');

    Route::livewire('/manajemen/rs/ri/laporan-surveilans-hais', 'pages::manajemen.rs.ri.laporan-surveilans-hais.laporan-surveilans-hais')
        ->name('manajemen.rs.ri.laporan-surveilans-hais');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-3-2-rawat-inap', 'pages::manajemen.sirs.ri.laporan-rl-3-2-rawat-inap.laporan-rl-3-2-rawat-inap')
        ->name('manajemen.sirs.ri.laporan-rl-3-2-rawat-inap');

    Route::livewire('/manajemen/sirs/ugd/laporan-rl-3-3-rawat-darurat', 'pages::manajemen.sirs.ugd.laporan-rl-3-3-rawat-darurat.laporan-rl-3-3-rawat-darurat')
        ->name('manajemen.sirs.ugd.laporan-rl-3-3-rawat-darurat');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-3-4-pengunjung', 'pages::manajemen.sirs.rj.laporan-rl-3-4-pengunjung.laporan-rl-3-4-pengunjung')
        ->name('manajemen.sirs.rj.laporan-rl-3-4-pengunjung');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-3-5-kunjungan', 'pages::manajemen.sirs.rj.laporan-rl-3-5-kunjungan.laporan-rl-3-5-kunjungan')
        ->name('manajemen.sirs.rj.laporan-rl-3-5-kunjungan');

    Route::livewire('/manajemen/sirs/penunjang/laporan-rl-3-8-laboratorium', 'pages::manajemen.sirs.penunjang.laporan-rl-3-8-laboratorium.laporan-rl-3-8-laboratorium')
        ->name('manajemen.sirs.penunjang.laporan-rl-3-8-laboratorium');

    Route::livewire('/manajemen/sirs/penunjang/laporan-rl-3-9-radiologi', 'pages::manajemen.sirs.penunjang.laporan-rl-3-9-radiologi.laporan-rl-3-9-radiologi')
        ->name('manajemen.sirs.penunjang.laporan-rl-3-9-radiologi');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-3-15-kesehatan-jiwa', 'pages::manajemen.sirs.rj.laporan-rl-3-15-kesehatan-jiwa.laporan-rl-3-15-kesehatan-jiwa')
        ->name('manajemen.sirs.rj.laporan-rl-3-15-kesehatan-jiwa');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-3-19-cara-bayar', 'pages::manajemen.sirs.ri.laporan-rl-3-19-cara-bayar.laporan-rl-3-19-cara-bayar')
        ->name('manajemen.sirs.ri.laporan-rl-3-19-cara-bayar');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-4-1-morbiditas', 'pages::manajemen.sirs.ri.laporan-rl-4-1-morbiditas.laporan-rl-4-1-morbiditas')
        ->name('manajemen.sirs.ri.laporan-rl-4-1-morbiditas');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-4-2-10besar', 'pages::manajemen.sirs.ri.laporan-rl-4-2-10besar.laporan-rl-4-2-10besar')
        ->name('manajemen.sirs.ri.laporan-rl-4-2-10besar');

    Route::livewire('/manajemen/sirs/ri/laporan-rl-4-3-10besar-mati', 'pages::manajemen.sirs.ri.laporan-rl-4-3-10besar-mati.laporan-rl-4-3-10besar-mati')
        ->name('manajemen.sirs.ri.laporan-rl-4-3-10besar-mati');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-5-1-morbiditas', 'pages::manajemen.sirs.rj.laporan-rl-5-1-morbiditas.laporan-rl-5-1-morbiditas')
        ->name('manajemen.sirs.rj.laporan-rl-5-1-morbiditas');

    Route::livewire('/manajemen/sirs/rj/laporan-rl-5-3-10besar-kunjungan', 'pages::manajemen.sirs.rj.laporan-rl-5-3-10besar-kunjungan.laporan-rl-5-3-10besar-kunjungan')
        ->name('manajemen.sirs.rj.laporan-rl-5-3-10besar-kunjungan');

    Route::livewire('/manajemen/rs/penunjang/lab/laporan-permintaan-lab', 'pages::manajemen.rs.penunjang.lab.laporan-permintaan-lab.laporan-permintaan-lab')
        ->name('manajemen.rs.penunjang.lab.laporan-permintaan-lab');

    Route::livewire('/manajemen/rs/penunjang/rad/laporan-permintaan-rad', 'pages::manajemen.rs.penunjang.rad.laporan-permintaan-rad.laporan-permintaan-rad')
        ->name('manajemen.rs.penunjang.rad.laporan-permintaan-rad');

    Route::livewire('/manajemen/rs/penunjang/ok/laporan-kasus-operasi', 'pages::manajemen.rs.penunjang.ok.laporan-kasus-operasi.laporan-kasus-operasi')
        ->name('manajemen.rs.penunjang.ok.laporan-kasus-operasi');

    Route::livewire('/manajemen/rs/penunjang/lab/laporan-pemeriksaan-lab', 'pages::manajemen.rs.penunjang.lab.laporan-pemeriksaan-lab.laporan-pemeriksaan-lab')
        ->name('manajemen.rs.penunjang.lab.laporan-pemeriksaan-lab');

    Route::livewire('/manajemen/rs/penunjang/lab/laporan-pemeriksaan-dalam-luar', 'pages::manajemen.rs.penunjang.lab.laporan-pemeriksaan-dalam-luar.laporan-pemeriksaan-dalam-luar')
        ->name('manajemen.rs.penunjang.lab.laporan-pemeriksaan-dalam-luar');

    Route::livewire('/manajemen/rs/penunjang/lab/laporan-nilai-kritis', 'pages::manajemen.rs.penunjang.lab.laporan-nilai-kritis.laporan-nilai-kritis')
        ->name('manajemen.rs.penunjang.lab.laporan-nilai-kritis');

    Route::livewire('/manajemen/rs/penunjang/rad/laporan-pemeriksaan-rad', 'pages::manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad.laporan-pemeriksaan-rad')
        ->name('manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad');

    Route::livewire('/manajemen/rs/penunjang/rad/laporan-pemeriksaan-rad-detail', 'pages::manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad-detail.laporan-pemeriksaan-rad-detail')
        ->name('manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad-detail');
});


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| REDIRECT URL LAMA -> BARU (penyeragaman prefix, docs/standar-struktur-folder.md §7)
|--------------------------------------------------------------------------
| Petugas menyimpan URL lama di bookmark & pintasan browser. Redirect ini menjaga
| keduanya tetap bekerja. Sengaja 302 (default Route::redirect), BUKAN 301: 301
| di-cache permanen oleh browser, jadi kalau nanti ada penyesuaian lagi, pengguna
| yang sudah pernah membuka URL lama akan sulit dilepas dari cache-nya.
|
| Boleh dihapus kalau sudah yakin tidak ada lagi yang memakai URL lama.
*/

Route::redirect('/rawat-jalan/daftar', '/rj/daftar');
Route::redirect('/rawat-jalan/pelayanan', '/rj/pelayanan');
Route::redirect('/rawat-jalan/daftar-bulanan', '/rj/daftar-bulanan');
Route::redirect('/rawat-jalan/booking', '/rj/booking');
Route::redirect('/jadwal-kontrol', '/kontrol/jadwal-kontrol');
Route::redirect('/transaksi/rj/antrian-apotek-rj', '/rj/antrian-apotek');
Route::redirect('/transaksi/rj/antrian-kasir-rj', '/rj/antrian-kasir');
Route::redirect('/transaksi/ugd/antrian-apotek-ugd', '/ugd/antrian-apotek');
Route::redirect('/transaksi/ugd/antrian-kasir-ugd', '/ugd/antrian-kasir');
Route::redirect('/transaksi/apotek', '/apotek');
Route::redirect('/transaksi/kasir', '/kasir');
Route::redirect('/transaksi/casemix', '/casemix');
Route::redirect('/transaksi/approval-hub', '/approval-hub');
Route::redirect('/transaksi/ri-resep/antrian-ri-resep', '/ri-resep/antrian');
Route::redirect('/transaksi/kasir/antrian-kasir-ri', '/kasir/antrian-ri');
Route::redirect('/transaksi/kasir/daftar-kasir-ri', '/kasir/daftar-ri');
Route::redirect('/transaksi/penunjang/laborat', '/penunjang/laborat');
Route::redirect('/transaksi/penunjang/laborat/lab-luar', '/penunjang/laborat/lab-luar');
Route::redirect('/transaksi/penunjang/radiologi/upload', '/penunjang/radiologi/upload');
Route::redirect('/transaksi/penunjang/kamar-operasi', '/penunjang/kamar-operasi');
Route::redirect('/ri/update-tt-ri', '/ri/update-tt');
