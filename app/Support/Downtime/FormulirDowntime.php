<?php

namespace App\Support\Downtime;

/**
 * FormulirDowntime — sumber tunggal daftar formulir manual waktu henti (down time) SIMRS.
 *
 * Dipakai halaman /downtime/formulir-manual: katalog, preview, unduh PDF per formulir,
 * dan unduh bundel per area untuk sosialisasi.
 *
 * Tambah/ubah formulir cukup di `definitions()`. Tiap entri:
 * - kode      : kode formulir yang dicetak di kop & footer (dipakai saat rekonsiliasi)
 * - judul     : nama formulir
 * - area      : kunci AREAS (menentukan tab & bundel)
 * - unit      : unit pengguna formulir
 * - desc      : ringkasan isi formulir (kartu katalog)
 * - isi       : poin-poin bagian formulir (kartu katalog)
 * - entriUlang: menu SIMRS tujuan entri ulang setelah sistem pulih
 * - view      : partial blade di resources/views/pages/downtime/cetak/form/
 * - lembar    : perkiraan jumlah lembar A4 per formulir
 */
class FormulirDowntime
{
    /** Area pelayanan → label tab. Urutan di sini = urutan tab & bundel. */
    public const AREAS = [
        'umum' => 'Umum / IT',
        'emr-rj' => 'EMR Rawat Jalan',
        'adm-rj' => 'Administrasi RJ',
        'emr-ugd' => 'EMR UGD',
        'adm-ugd' => 'Administrasi UGD',
        'emr-ri' => 'EMR Rawat Inap',
        'adm-ri' => 'Administrasi RI',
        'kasir' => 'Kasir (RJ/UGD/RI)',
        'apotek' => 'Apotek (RJ/UGD/RI)',
    ];

    /** Prefix folder blade partial formulir. */
    private const VIEW_PREFIX = 'pages.downtime.cetak.form.';

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return self::definitions();
    }

    /** @return array<int, array<string, mixed>> */
    public static function byArea(string $area): array
    {
        if ($area === '' || $area === 'semua') {
            return self::all();
        }

        return array_values(array_filter(self::all(), fn($f) => $f['area'] === $area));
    }

    /** @return array<string, mixed>|null */
    public static function find(string $kode): ?array
    {
        foreach (self::all() as $form) {
            if ($form['kode'] === $kode) {
                return $form;
            }
        }

        return null;
    }

    /** Nama blade lengkap partial formulir. */
    public static function viewName(array $form): string
    {
        return self::VIEW_PREFIX . $form['view'];
    }

    /** Label area (fallback ke kunci bila tak dikenal). */
    public static function labelArea(string $area): string
    {
        return self::AREAS[$area] ?? $area;
    }

    /** Jumlah formulir per area — untuk badge tab. */
    public static function jumlahPerArea(): array
    {
        $jumlah = [];
        foreach (self::all() as $form) {
            $jumlah[$form['area']] = ($jumlah[$form['area']] ?? 0) + 1;
        }

        return $jumlah;
    }

    /** @return array<int, array<string, mixed>> */
    private static function definitions(): array
    {
        return [
            // ── Umum / IT ─────────────────────────────────────────────
            [
                'kode' => 'DT-01',
                'judul' => 'Log Kejadian & Penanganan Down Time SIMRS',
                'area' => 'umum',
                'unit' => 'Unit IT / Penyelenggara SIMRS',
                'desc' => 'Catatan kejadian gangguan: waktu mulai & pulih, durasi, penyebab, dampak pelayanan, tindakan penanganan, dan rencana tindak lanjut.',
                'isi' => ['Identitas kejadian & pelapor', 'Kronologi dan durasi', 'Penyebab & dampak per unit', 'Tindakan penanganan', 'Evaluasi & rencana tindak lanjut'],
                'entriUlang' => 'Diarsipkan Unit IT — bahan laporan evaluasi ke pimpinan RS',
                'view' => 'dt-01-log-kejadian',
                'lembar' => 1,
            ],
            [
                'kode' => 'DT-02',
                'judul' => 'Pemberitahuan & Ceklist Persiapan Down Time Terencana',
                'area' => 'umum',
                'unit' => 'Unit IT & seluruh unit pelayanan',
                'desc' => 'Surat pemberitahuan waktu henti terencana (minimal 3 hari sebelumnya) sekaligus ceklist kesiapan formulir manual tiap unit.',
                'isi' => ['Jadwal & lingkup waktu henti', 'Tanda terima pemberitahuan per unit', 'Ceklist kesiapan formulir manual', 'Tanda tangan Ka. Unit & Unit IT'],
                'entriUlang' => 'Diarsipkan Unit IT bersama DT-01',
                'view' => 'dt-02-pemberitahuan',
                'lembar' => 1,
            ],
            [
                'kode' => 'DT-03',
                'judul' => 'Ceklist Rekonsiliasi & Entri Ulang Data Pasca Down Time',
                'area' => 'umum',
                'unit' => 'Seluruh unit pelayanan + Rekam Medis',
                'desc' => 'Ceklist verifikasi bahwa seluruh berkas manual selama waktu henti sudah dientri ulang ke SIMRS, lengkap dan akurat.',
                'isi' => ['Rekap jumlah berkas manual per formulir', 'Status entri ulang & petugas entri', 'Verifikasi kelengkapan oleh supervisor', 'Catatan selisih / data belum lengkap'],
                'entriUlang' => 'Diarsipkan unit masing-masing, salinan ke Unit IT & Rekam Medis',
                'view' => 'dt-03-rekonsiliasi',
                'lembar' => 1,
            ],

            // ── EMR Rawat Jalan ───────────────────────────────────────
            [
                'kode' => 'RJ-EMR-01',
                'judul' => 'Asesmen Awal Keperawatan Rawat Jalan',
                'area' => 'emr-rj',
                'unit' => 'Perawat poli rawat jalan',
                'desc' => 'Pengganti tab Anamnesa & Pemeriksaan (perawat) pada EMR RJ: keluhan utama, tanda vital, nutrisi, riwayat & alergi, status psikologis, fungsional.',
                'isi' => ['Keluhan utama & jam datang', 'Tanda vital + tingkat kesadaran', 'Nutrisi: BB, TB, IMT, LK, LILA', 'Riwayat penyakit dahulu & alergi', 'Status psikologis & fungsional'],
                'entriUlang' => 'Pelayanan Rawat Jalan > EMR > tab Anamnesa & Pemeriksaan',
                'view' => 'rj-emr-01-asesmen-perawat',
                'lembar' => 1,
            ],
            [
                'kode' => 'RJ-EMR-02',
                'judul' => 'Asesmen Medis, Diagnosa & Terapi Rawat Jalan',
                'area' => 'emr-rj',
                'unit' => 'Dokter poli rawat jalan',
                'desc' => 'Pengganti tab Pemeriksaan (dokter), Diagnosa dan Perencanaan pada EMR RJ: pemeriksaan fisik, diagnosa ICD-10, prosedur, terapi & tindak lanjut.',
                'isi' => ['Riwayat penyakit sekarang', 'Pemeriksaan fisik & lokalis', 'Diagnosa primer / sekunder (ICD-10)', 'Prosedur / tindakan (ICD-9-CM)', 'Terapi, edukasi & tindak lanjut'],
                'entriUlang' => 'Pelayanan Rawat Jalan > EMR > tab Pemeriksaan, Diagnosa, Perencanaan',
                'view' => 'rj-emr-02-asesmen-medis',
                'lembar' => 1,
            ],
            [
                'kode' => 'RJ-EMR-03',
                'judul' => 'Penilaian Nyeri, Risiko Jatuh & Skrining Gizi Rawat Jalan',
                'area' => 'emr-rj',
                'unit' => 'Perawat poli rawat jalan',
                'desc' => 'Pengganti tab Penilaian pada EMR RJ: skor nyeri (NRS/FLACC), risiko jatuh (Morse / Humpty Dumpty), dan skrining gizi awal.',
                'isi' => ['Penilaian nyeri: metode, skor, lokasi, intervensi', 'Risiko jatuh dewasa (Skala Morse)', 'Risiko jatuh anak (Humpty Dumpty)', 'Skrining gizi awal & rekomendasi'],
                'entriUlang' => 'Pelayanan Rawat Jalan > EMR > tab Penilaian',
                'view' => 'rj-emr-03-penilaian',
                'lembar' => 1,
            ],
            [
                'kode' => 'RJ-EMR-04',
                'judul' => 'Permintaan Pemeriksaan Penunjang Rawat Jalan',
                'area' => 'emr-rj',
                'unit' => 'Dokter poli — tujuan Laboratorium / Radiologi',
                'desc' => 'Pengganti order penunjang elektronik. Keterangan klinis wajib diisi karena menjadi syarat order laboratorium & radiologi di SIMRS.',
                'isi' => ['Keterangan klinis (wajib)', 'Daftar pemeriksaan laboratorium', 'Daftar pemeriksaan radiologi', 'Status cito / rutin & jam order', 'Serah terima sampel / pasien ke unit penunjang'],
                'entriUlang' => 'Pelayanan RJ > Pemeriksaan > Penunjang; hasil di Transaksi Laboratorium / Upload Hasil Radiologi',
                'view' => 'rj-emr-04-penunjang',
                'lembar' => 1,
            ],

            // ── Administrasi Rawat Jalan ──────────────────────────────
            [
                'kode' => 'RJ-ADM-01',
                'judul' => 'Pendaftaran Pasien Rawat Jalan',
                'area' => 'adm-rj',
                'unit' => 'Pendaftaran / TU',
                'desc' => 'Pengganti layar Daftar Rawat Jalan: identitas pasien, poli & dokter tujuan, cara bayar, data rujukan / SEP BPJS.',
                'isi' => ['Nomor urut & jam daftar', 'Identitas pasien (pasien baru / lama)', 'Poli, dokter & jenis kunjungan', 'Cara bayar: umum / BPJS / asuransi lain', 'Data rujukan, no. SEP & kontrol (SKDP)'],
                'entriUlang' => 'Rawat Jalan > Daftar Rawat Jalan (pasien baru: Master Pasien lebih dulu)',
                'view' => 'rj-adm-01-pendaftaran',
                'lembar' => 1,
            ],
            [
                'kode' => 'RJ-ADM-02',
                'judul' => 'Rincian Biaya Pelayanan Rawat Jalan',
                'area' => 'adm-rj',
                'unit' => 'Administrasi rawat jalan',
                'desc' => 'Pengganti layar Administrasi RJ: rincian jasa medis, jasa dokter, jasa karyawan, laboratorium, radiologi, obat dan lain-lain sebelum ke kasir.',
                'isi' => ['Administrasi & jasa medis', 'Jasa dokter & jasa karyawan', 'Laboratorium & radiologi', 'Obat / alkes', 'Lain-lain dan total tagihan'],
                'entriUlang' => 'Rawat Jalan > Daftar Rawat Jalan > Administrasi RJ (per tab biaya)',
                'view' => 'rj-adm-02-rincian-biaya',
                'lembar' => 1,
            ],
            [
                'kode' => 'RJ-ADM-03',
                'judul' => 'Register Harian Kunjungan Rawat Jalan',
                'area' => 'adm-rj',
                'unit' => 'Pendaftaran / TU',
                'desc' => 'Buku bantu urutan kunjungan selama waktu henti — dipakai memastikan tidak ada pasien terlewat saat entri ulang.',
                'isi' => ['20 baris kunjungan per lembar', 'Jam, No. RM, nama, poli & dokter', 'Cara bayar & no. SEP', 'Kolom centang sudah dientri ulang'],
                'entriUlang' => 'Dicocokkan dengan Rawat Jalan > Daftar Rawat Jalan pada tanggal yang sama',
                'view' => 'rj-adm-03-register',
                'lembar' => 1,
            ],

            // ── EMR Unit Gawat Darurat ────────────────────────────────
            [
                'kode' => 'UGD-EMR-01',
                'judul' => 'Triase & Asesmen Awal Keperawatan UGD',
                'area' => 'emr-ugd',
                'unit' => 'Perawat UGD',
                'desc' => 'Pengganti triase + tab Anamnesa & Pemeriksaan (perawat) pada EMR UGD: penetapan prioritas P0-P3, survei primer, tanda vital, alergi, nyeri & risiko jatuh.',
                'isi' => ['Triase P0 / P1 / P2 / P3 + jam triase', 'Cara datang & pengantar', 'Survei primer (airway, breathing, circulation, disability)', 'Tanda vital + GCS (E, V, M)', 'Keluhan utama, alergi, nyeri & risiko jatuh'],
                'entriUlang' => 'Pelayanan UGD → EMR → triase, tab Anamnesa & Pemeriksaan',
                'view' => 'ugd-emr-01-triase-asesmen',
                'lembar' => 1,
            ],
            [
                'kode' => 'UGD-EMR-02',
                'judul' => 'Asesmen Medis, Diagnosa & Tindakan UGD',
                'area' => 'emr-ugd',
                'unit' => 'Dokter jaga UGD',
                'desc' => 'Pengganti tab Pemeriksaan (dokter), Diagnosa dan Perencanaan pada EMR UGD: pemeriksaan fisik, diagnosa kerja, tindakan kegawatdaruratan dan disposisi pasien.',
                'isi' => ['Anamnesis & riwayat', 'Pemeriksaan fisik / status lokalis', 'Diagnosa kerja & banding (ICD-10)', 'Tindakan kegawatdaruratan (ICD-9-CM)', 'Disposisi: pulang / rawat inap / rujuk / meninggal'],
                'entriUlang' => 'Pelayanan UGD → EMR → tab Pemeriksaan, Diagnosa, Perencanaan',
                'view' => 'ugd-emr-02-asesmen-medis',
                'lembar' => 1,
            ],
            [
                'kode' => 'UGD-EMR-03',
                'judul' => 'Lembar Observasi & Pemberian Obat / Cairan UGD',
                'area' => 'emr-ugd',
                'unit' => 'Perawat UGD',
                'desc' => 'Pengganti tab Observasi dan Obat & Cairan pada EMR UGD: pemantauan berkala tanda vital selama di UGD dan catatan pemberian obat/cairan infus.',
                'isi' => ['Observasi berkala: jam, TD, nadi, RR, suhu, SpO2, GCS', 'Cairan & tetesan infus', 'Pemberian obat: nama, dosis, rute, jam', 'Paraf pelaksana tiap baris'],
                'entriUlang' => 'Pelayanan UGD → EMR → tab Observasi dan Obat & Cairan',
                'view' => 'ugd-emr-03-observasi',
                'lembar' => 1,
            ],
            [
                'kode' => 'UGD-EMR-04',
                'judul' => 'Permintaan Pemeriksaan Penunjang UGD (Cito)',
                'area' => 'emr-ugd',
                'unit' => 'Dokter UGD → Laboratorium / Radiologi',
                'desc' => 'Pengganti order penunjang elektronik untuk pasien gawat darurat. Keterangan klinis wajib diisi karena menjadi syarat order di SIMRS.',
                'isi' => ['Keterangan klinis (wajib) & status cito', 'Daftar pemeriksaan laboratorium', 'Daftar pemeriksaan radiologi', 'Serah terima sampel / pasien ke unit penunjang', 'Pelaporan nilai kritis ke DPJP'],
                'entriUlang' => 'Pelayanan UGD → Pemeriksaan → Penunjang; hasil di Transaksi Laboratorium / Upload Hasil Radiologi',
                'view' => 'ugd-emr-04-penunjang',
                'lembar' => 1,
            ],
            [
                'kode' => 'UGD-EMR-05',
                'judul' => 'Transfer / Rujukan Pasien UGD',
                'area' => 'emr-ugd',
                'unit' => 'Perawat & dokter UGD',
                'desc' => 'Serah terima pasien UGD ke rawat inap, ruang tindakan, atau rujukan ke RS lain — dua sisi (pengirim dan penerima) sesuai pola transfer di SIMRS.',
                'isi' => ['Tujuan transfer: RI / OK / VK / rujuk RS lain', 'Kondisi & tanda vital saat transfer', 'Alat terpasang, obat & dokumen yang diserahkan', 'Petugas pendamping & transportasi', 'Tanda tangan pengirim dan penerima'],
                'entriUlang' => 'Transfer ke RI: Daftar Rawat Inap (cara masuk UGD); rujuk keluar: EMR UGD → Rujukan Antar RS',
                'view' => 'ugd-emr-05-transfer',
                'lembar' => 1,
            ],

            // ── Administrasi Unit Gawat Darurat ───────────────────────
            [
                'kode' => 'UGD-ADM-01',
                'judul' => 'Pendaftaran Pasien UGD',
                'area' => 'adm-ugd',
                'unit' => 'Pendaftaran / TU',
                'desc' => 'Pengganti layar Daftar UGD: identitas pasien, jam kedatangan, cara masuk, penjaminan, serta data kasus kecelakaan / medikolegal.',
                'isi' => ['Jam datang & nomor urut manual', 'Identitas pasien (baru / lama)', 'Cara datang & pengantar', 'Cara bayar, no. kartu BPJS & SEP gawat darurat', 'Kasus laka lantas / medikolegal & pelapor'],
                'entriUlang' => 'UGD → Daftar UGD (pasien baru: Master Pasien lebih dulu)',
                'view' => 'ugd-adm-01-pendaftaran',
                'lembar' => 1,
            ],
            [
                'kode' => 'UGD-ADM-02',
                'judul' => 'Rincian Biaya Pelayanan UGD',
                'area' => 'adm-ugd',
                'unit' => 'Administrasi UGD',
                'desc' => 'Pengganti layar Administrasi UGD: rincian jasa medis, jasa dokter, tindakan kegawatdaruratan, penunjang, obat & alkes sebelum ke kasir.',
                'isi' => ['Administrasi & jasa UGD', 'Jasa dokter & jasa karyawan', 'Tindakan / jasa medis', 'Laboratorium, radiologi, obat & alkes', 'Total tagihan & penjaminan'],
                'entriUlang' => 'UGD → Daftar UGD → Administrasi UGD (per tab biaya)',
                'view' => 'ugd-adm-02-rincian-biaya',
                'lembar' => 1,
            ],
            [
                'kode' => 'UGD-ADM-03',
                'judul' => 'Register Harian Kunjungan UGD',
                'area' => 'adm-ugd',
                'unit' => 'Pendaftaran / TU & perawat UGD',
                'desc' => 'Buku bantu urutan kunjungan UGD selama waktu henti, lengkap dengan prioritas triase dan disposisi pasien.',
                'isi' => ['20 baris kunjungan per lembar', 'Jam datang, No. RM & nama', 'Triase P0-P3 & dokter jaga', 'Cara bayar & disposisi (pulang/RI/rujuk)', 'Kolom centang sudah dientri ulang'],
                'entriUlang' => 'Dicocokkan dengan UGD → Daftar UGD pada tanggal yang sama',
                'view' => 'ugd-adm-03-register',
                'lembar' => 1,
            ],

            // ── EMR Rawat Inap ────────────────────────────────────────
            [
                'kode' => 'RI-EMR-01',
                'judul' => 'Asesmen Awal Keperawatan Rawat Inap',
                'area' => 'emr-ri',
                'unit' => 'Perawat ruangan',
                'desc' => 'Pengganti Pengkajian Awal pada EMR RI: data masuk ruangan, keluhan, tanda vital, riwayat & alergi, penilaian nyeri, risiko jatuh, gizi dan risiko dekubitus.',
                'isi' => ['Jam masuk ruangan & asal pasien', 'Keluhan utama & riwayat penyakit', 'Tanda vital, BB/TB & alergi', 'Penilaian nyeri, risiko jatuh & skrining gizi', 'Risiko dekubitus (Braden) & orientasi ruangan'],
                'entriUlang' => 'Daftar Rawat Inap → EMR → Pengkajian Awal & tab Penilaian',
                'view' => 'ri-emr-01-asesmen-perawat',
                'lembar' => 1,
            ],
            [
                'kode' => 'RI-EMR-02',
                'judul' => 'Asesmen Awal Medis Rawat Inap (DPJP)',
                'area' => 'emr-ri',
                'unit' => 'DPJP / dokter jaga ruangan',
                'desc' => 'Pengganti Pengkajian Dokter pada EMR RI: anamnesis, pemeriksaan fisik, diagnosa kerja, rencana terapi dan rencana pemulangan.',
                'isi' => ['Anamnesis & riwayat penyakit', 'Pemeriksaan fisik per sistem', 'Diagnosa kerja & banding (ICD-10)', 'Rencana pemeriksaan, terapi & tindakan', 'Perkiraan lama rawat & rencana pulang'],
                'entriUlang' => 'Daftar Rawat Inap → EMR → Pengkajian Dokter & tab Diagnosa',
                'view' => 'ri-emr-02-asesmen-medis',
                'lembar' => 1,
            ],
            [
                'kode' => 'RI-EMR-03',
                'judul' => 'Catatan Perkembangan Pasien Terintegrasi (CPPT)',
                'area' => 'emr-ri',
                'unit' => 'Seluruh PPA (dokter, perawat, apoteker, gizi, fisioterapi)',
                'desc' => 'Pengganti CPPT elektronik: catatan SOAP per PPA, instruksi, serta kolom review / verifikasi DPJP utama.',
                'isi' => ['Tanggal, jam & profesi PPA', 'S — subjektif, O — objektif', 'A — asesmen, P — plan', 'Instruksi PPA & paraf', 'Kolom review / verifikasi DPJP'],
                'entriUlang' => 'Daftar Rawat Inap → EMR → CPPT (entri per catatan, tanggal & profesi sesuai tulisan tangan)',
                'view' => 'ri-emr-03-cppt',
                'lembar' => 1,
            ],
            [
                'kode' => 'RI-EMR-04',
                'judul' => 'SBAR — Serah Terima Pasien Antar Shift',
                'area' => 'emr-ri',
                'unit' => 'Perawat ruangan',
                'desc' => 'Pengganti SBAR elektronik: serah terima kondisi pasien antar shift dengan format Situation, Background, Assessment, Recommendation.',
                'isi' => ['Tanggal, jam & shift', 'S — situation (kondisi saat ini)', 'B — background (riwayat singkat)', 'A — assessment (hasil penilaian)', 'R — recommendation + TTD kedua perawat'],
                'entriUlang' => 'Daftar Rawat Inap → EMR → SBAR (entri per catatan sesuai tanggal & profesi)',
                'view' => 'ri-emr-04-sbar',
                'lembar' => 1,
            ],
            [
                'kode' => 'RI-EMR-05',
                'judul' => 'Observasi Tanda Vital & Balance Cairan Harian',
                'area' => 'emr-ri',
                'unit' => 'Perawat ruangan',
                'desc' => 'Pengganti tab Observasi pada EMR RI: pemantauan tanda vital berkala, intake-output cairan, oksigen dan alat invasif terpasang.',
                'isi' => ['Observasi per jam: TD, nadi, RR, suhu, SpO2', 'Intake: oral, infus, obat', 'Output: urine, muntah, drain, feses', 'Balance cairan per shift & 24 jam', 'Pemakaian oksigen & alat invasif'],
                'entriUlang' => 'Daftar Rawat Inap → EMR → Observasi (observasi lanjutan, cairan, oksigen, alat invasif)',
                'view' => 'ri-emr-05-observasi',
                'lembar' => 1,
            ],
            [
                'kode' => 'RI-EMR-06',
                'judul' => 'Rekam Pemberian Obat Rawat Inap',
                'area' => 'emr-ri',
                'unit' => 'Perawat & apoteker ruangan',
                'desc' => 'Catatan pemberian obat per pasien selama waktu henti — dasar penagihan obat dan penyesuaian stok ruangan setelah sistem pulih.',
                'isi' => ['Nama obat, dosis & rute', 'Jadwal pemberian pagi / siang / sore / malam', 'Paraf pemberi tiap dosis', 'Obat yang ditunda / tidak diberikan + alasan', 'Verifikasi apoteker'],
                'entriUlang' => 'Daftar Rawat Inap → E-Resep / Administrasi RI (obat), lalu cek Kartu Stock ruangan',
                'view' => 'ri-emr-06-pemberian-obat',
                'lembar' => 1,
            ],
            [
                'kode' => 'RI-EMR-07',
                'judul' => 'Ringkasan Pulang (Resume Medis) Rawat Inap',
                'area' => 'emr-ri',
                'unit' => 'DPJP',
                'desc' => 'Pengganti Ringkasan Pulang elektronik — dokumen wajib rekam medis dan syarat berkas klaim BPJS, diisi DPJP saat pasien pulang.',
                'isi' => ['Tanggal masuk & keluar, lama rawat', 'Indikasi rawat & riwayat singkat', 'Diagnosa utama & sekunder (ICD-10)', 'Tindakan / prosedur (ICD-9-CM)', 'Obat pulang, kondisi pulang & rencana kontrol'],
                'entriUlang' => 'Daftar Rawat Inap → EMR → Ringkasan Pulang; berkas klaim menyusul setelah lengkap',
                'view' => 'ri-emr-07-ringkasan-pulang',
                'lembar' => 1,
            ],

            // ── Administrasi Rawat Inap ───────────────────────────────
            [
                'kode' => 'RI-ADM-01',
                'judul' => 'Pendaftaran & Penjaminan Rawat Inap',
                'area' => 'adm-ri',
                'unit' => 'Pendaftaran / TU',
                'desc' => 'Pengganti layar Daftar Rawat Inap: identitas pasien, cara masuk, ruangan & kelas, DPJP, penjaminan serta persetujuan biaya.',
                'isi' => ['Tanggal & jam masuk, cara masuk (UGD/RJ/rujukan)', 'Bangsal, kamar & kelas rawat', 'DPJP dan diagnosa masuk', 'Cara bayar, kelas hak, no. SEP & SPRI', 'Penanggung jawab biaya & uang muka'],
                'entriUlang' => 'Rawat Inap → Daftar Rawat Inap (pasien baru: Master Pasien lebih dulu)',
                'view' => 'ri-adm-01-pendaftaran',
                'lembar' => 1,
            ],
            [
                'kode' => 'RI-ADM-02',
                'judul' => 'Rincian Biaya Pelayanan Rawat Inap',
                'area' => 'adm-ri',
                'unit' => 'Administrasi rawat inap',
                'desc' => 'Pengganti layar Administrasi RI: akomodasi kamar per hari, visite & konsul, tindakan, penunjang, obat, serta uang muka dan sisa tagihan.',
                'isi' => ['Akomodasi kamar per kelas & jumlah hari', 'Visite dokter & konsul', 'Tindakan / jasa medis & jasa karyawan', 'Laboratorium, radiologi, OK, obat & alkes', 'Uang muka, total tagihan & sisa bayar'],
                'entriUlang' => 'Rawat Inap → Daftar Rawat Inap → Administrasi RI (per tab biaya)',
                'view' => 'ri-adm-02-rincian-biaya',
                'lembar' => 1,
            ],
            [
                'kode' => 'RI-ADM-03',
                'judul' => 'Sensus Harian & Mutasi Kamar Rawat Inap',
                'area' => 'adm-ri',
                'unit' => 'Perawat ruangan & rekam medis',
                'desc' => 'Rekap harian pasien per ruangan: pasien awal, masuk, pindah, keluar dan sisa — dasar sinkronisasi tempat tidur serta indikator BOR setelah sistem pulih.',
                'isi' => ['Rekap pasien awal / masuk / keluar / sisa', 'Daftar pasien per kamar & bed', 'Mutasi pindah kamar & alasannya', 'Tempat tidur kosong per kelas', 'Kolom centang sudah dientri ulang'],
                'entriUlang' => 'Rawat Inap → Daftar Rawat Inap (pindah kamar) & Sinkronisasi Tempat Tidur',
                'view' => 'ri-adm-03-sensus',
                'lembar' => 1,
            ],

            // ── Kasir (lintas jalur RJ/UGD/RI) ────────────────────────
            [
                'kode' => 'KSR-01',
                'judul' => 'Kwitansi Pembayaran Manual',
                'area' => 'kasir',
                'unit' => 'Kasir RJ / UGD / RI',
                'desc' => 'Kwitansi manual rangkap dua dalam satu lembar (lembar pasien & arsip kasir), lengkap dengan rincian dan terbilang. Jalur pelayanan dicentang di formulir.',
                'isi' => ['Pilihan jalur: RJ / UGD / RI', 'Nomor kwitansi manual & tanggal', 'Identitas pasien & unit', 'Rincian biaya ringkas + terbilang', 'Tanda tangan kasir dan penerima'],
                'entriUlang' => 'Kasir → Antrian Kasir RJ / UGD / RI; nomor kwitansi manual dicatat di keterangan',
                'view' => 'ksr-01-kwitansi',
                'lembar' => 1,
            ],
            [
                'kode' => 'KSR-02',
                'judul' => 'Register Penerimaan Kasir',
                'area' => 'kasir',
                'unit' => 'Kasir RJ / UGD / RI',
                'desc' => 'Rekap seluruh penerimaan kasir selama waktu henti per shift — dasar pencocokan uang fisik dengan entri ulang di SIMRS.',
                'isi' => ['20 baris transaksi per lembar', 'Kolom jalur (RJ/UGD/RI) & No. kwitansi', 'Nominal tunai / non-tunai / piutang', 'Subtotal per lembar & total shift', 'Kolom centang sudah dientri ulang'],
                'entriUlang' => 'Dicocokkan dengan Kasir → Antrian Kasir tiap jalur dan Keuangan → Saldo Kas',
                'view' => 'ksr-02-register',
                'lembar' => 1,
            ],
            [
                'kode' => 'KSR-03',
                'judul' => 'Berita Acara Serah Terima Kas Shift Kasir',
                'area' => 'kasir',
                'unit' => 'Kasir & supervisor TU',
                'desc' => 'Serah terima uang fisik antar shift selama waktu henti, termasuk rincian pecahan uang dan selisih.',
                'isi' => ['Identitas shift, penyerah & penerima', 'Rincian pecahan uang', 'Total register vs uang fisik & selisih', 'Catatan transaksi tertunda', 'Tanda tangan kedua pihak & saksi'],
                'entriUlang' => 'Diarsipkan kasir; nilai akhir dicocokkan ke Keuangan → Saldo Kas',
                'view' => 'ksr-03-serah-terima',
                'lembar' => 1,
            ],

            // ── Apotek (lintas jalur RJ/UGD/RI) ───────────────────────
            [
                'kode' => 'APT-01',
                'judul' => 'Resep Manual, Telaah Resep & Telaah Obat',
                'area' => 'apotek',
                'unit' => 'Dokter & apoteker RJ / UGD / RI',
                'desc' => 'Pengganti e-resep: penulisan resep non racikan & racikan, pengkajian resep 10 item dan pengkajian obat 4 item sesuai form cetak e-resep. Jalur dicentang di formulir.',
                'isi' => ['Pilihan jalur: RJ / UGD / RI', 'Resep non racikan (R/, jumlah, signa)', 'Resep racikan per nomor racikan', 'Pengkajian resep 10 butir & obat 4 butir', 'TTD dokter, pengkaji resep/obat & penerima'],
                'entriUlang' => 'Pelayanan RJ/UGD/RI → E-Resep, lalu Apotek → Antrian Apotek jalur terkait (telaah resep & obat)',
                'view' => 'apt-01-resep',
                'lembar' => 1,
            ],
            [
                'kode' => 'APT-02',
                'judul' => 'Etiket Obat Manual',
                'area' => 'apotek',
                'unit' => 'Apotek RJ / UGD / RI',
                'desc' => 'Lembar etiket potong (8 etiket per lembar) untuk penyerahan obat saat pencetakan etiket dari SIMRS tidak tersedia.',
                'isi' => ['8 etiket siap potong per lembar', 'No. RM, nama & tanggal', 'Aturan pakai & waktu minum', 'Catatan khusus (sebelum/sesudah makan)', 'Kolom kedaluwarsa & petugas'],
                'entriUlang' => 'Tidak perlu dientri — etiket tercetak otomatis saat e-resep dientri ulang',
                'view' => 'apt-02-etiket',
                'lembar' => 1,
            ],
            [
                'kode' => 'APT-03',
                'judul' => 'Register Pengeluaran Obat & Penyesuaian Stok',
                'area' => 'apotek',
                'unit' => 'Apotek RJ / UGD / RI',
                'desc' => 'Catatan manual pengeluaran obat per pasien selama waktu henti — dasar penyesuaian kartu stok apotek setelah sistem pulih.',
                'isi' => ['20 baris pengeluaran per lembar', 'Kolom jalur (RJ/UGD/RI)', 'No. RM / nama pasien & nama obat', 'Jumlah, satuan & sisa stok fisik', 'Kolom centang sudah dientri ulang'],
                'entriUlang' => 'E-resep dientri ulang → stok terpotong otomatis; sisa selisih lewat Gudang → Kartu Stock Apotek',
                'view' => 'apt-03-register-obat',
                'lembar' => 1,
            ],
        ];
    }
}
