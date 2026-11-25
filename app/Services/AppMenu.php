<?php

namespace App\Services;

use App\Models\User;
use App\Support\AksesHalaman;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * AppMenu — sumber tunggal definisi menu aplikasi (dashboard + sidebar).
 *
 * - `all()` mengembalikan semua entri (defensive: skip route yang tidak terdaftar)
 * - `forUser($user)` filter menurut hak akses (App\Support\AksesHalaman)
 * - `grouped($user)` filter + sort + group by 'group' untuk render UI.
 *
 * Tambah/edit menu di method `definitions()` saja. Dashboard & sidebar otomatis ikut.
 */
class AppMenu
{
    /**
     * Petugas yang boleh MENDAFTARKAN / MENGUBAH pendaftaran RJ, UGD, RI — centang awal
     * Tambah & Ubah Daftar RJ/UGD/RI (dulu @hasanyrole literal di tombol Daftar Baru & Ubah).
     */
    private const ROLES_PENDAFTARAN = ['mr', 'admin', 'supervisor tu'];

    /**
     * Petugas yang boleh mengentri transaksi Kamar Operasi (dulu KamarOperasiTrait::isAllowedRoleOk).
     * Dokter/Casemix/Mr/Gizi tidak — mereka hanya membuka Dokumen Pelayanan Bedah.
     */
    /**
     * Pengawas transaksi keuangan — centang awal Hapus hutang/DP & Ubah saldo kas.
     * Sama dengan AksiRole::PENGAWAS_KEUANGAN (keputusan user 2026-11-25): Supervisor Tu +
     * manager ke atas, manager = Manager Umum saja.
     */
    private const ROLES_PENGAWAS_KEUANGAN = ['admin', 'manager umum', 'supervisor tu'];

    private const ROLES_TRANSAKSI_OK = ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'perawat'];

    /** Role yang berhak melihat semua menu Master. */
    private const MASTER_ROLES = ['admin', 'manager umum', 'manager medis', 'supervisor penunjang', 'supervisor tu'];

    /**
     * Penanda 'roles' untuk menu yang boleh dilihat SEMUA role (juga user tanpa role).
     * Dipakai menu lintas unit seperti Formulir Manual Down Time — saat SIMRS
     * bermasalah, siapa pun yang masih bisa membuka aplikasi harus dapat
     * mengunduh formulir cadangannya.
     */
    public const SEMUA_ROLE = '*';

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        $entry = function (array $m): ?array {
            if (!Route::has($m['route'])) {
                return null;
            }
            $m['href'] = route($m['route'], $m['params'] ?? []);
            return $m;
        };

        return array_values(array_filter(self::definitions($entry)));
    }

    /**
     * Menu yang boleh dilihat user — keputusan di App\Support\AksesHalaman (centang "Lihat"
     * per role di Role Control), SAMA dengan penjaga URL CekAksesHalaman. Jadi menu yang
     * tampil = halaman yang boleh dibuka; tidak ada lagi menu tersembunyi tapi URL-nya terbuka.
     *
     * Kolom 'roles' di definitions() kini hanya isi awal (perintah akses:sinkron) dan
     * cadangan bila permission route itu belum dibuat.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return array_values(array_filter(
            self::all(),
            fn ($m) => AksesHalaman::boleh($user, $m['route']),
        ));
    }

    /** Sorted + grouped by 'group'. Cocok untuk render sidebar/dashboard. */
    public static function grouped(?User $user): Collection
    {
        return collect(self::forUser($user))
            ->sortBy([['groupOrder', 'asc'], ['order', 'asc']])
            ->groupBy('group');
    }

    /**
     * Definisi semua menu. Helper $entry guard route existence + inject 'href'.
     * Setiap entry: ['group','groupOrder','order','route','title','desc','roles','badge'].
     *
     * Opsional (hak akses, lihat docs/standar-hak-akses.md):
     *  - 'aksi'      => aksi yang BERLAKU di halaman ini (default keempatnya: lihat/tambah/ubah/hapus);
     *                   kolom lain tampil "—" di Role Control dan tidak dibuat permission-nya.
     *  - 'rolesAksi' => centang awal per aksi bila beda dari 'roles' (mis. hanya petugas
     *                   pendaftaran yang boleh Tambah walau banyak role boleh Lihat).
     *  - 'khusus'    => tombol khusus halaman: kunci => [label, roles awal]; permission
     *                   `{route}.{kunci}`, kolom Khusus di baris halaman ini (mis. tombol
     *                   pembuka Administrasi / Modul Dokumen dari daftar).
     *
     * @param  callable(array):?array  $entry
     * @return array<int, ?array<string, mixed>>
     */
    private static function definitions(callable $entry): array
    {
        $masterRoles = self::MASTER_ROLES;

        return [
            // ── Laporan Manajemen ──────────────────────────────────────
            // Dulu dibuka dari 5 halaman hub (Indikator Pelayanan/Penunjang/TU, Monitoring
            // Keuangan, Laporan Diagnosa) yang isinya hanya kartu tautan; hub dihapus
            // 2026-11-25 dan tiap laporan jadi menu sendiri (centang Lihat per laporan).
            // Roles awal = role hub asalnya (gabungan bila dulu dibuka dari >1 hub).
            // Semua groupOrder 0; urutan antar-grup lewat rentang 'order' (100/200/...).
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 101, 'route' => 'manajemen.rs.rj.laporan-kunjungan-rj', 'aksi' => ['lihat'], 'title' => 'Laporan Kunjungan RJ', 'desc' => 'Tahunan / Multi-Tahun — BPJS/UMUM, pasien baru/lama, breakdown poli', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RJ']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 102, 'route' => 'manajemen.rs.ugd.laporan-kunjungan-ugd', 'aksi' => ['lihat'], 'title' => 'Laporan Kunjungan UGD', 'desc' => 'Tahunan / Multi-Tahun — BPJS/UMUM, pasien baru/lama, breakdown dokter, triase P1-P4', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'UGD']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 103, 'route' => 'manajemen.rs.ri.laporan-kunjungan-ri', 'aksi' => ['lihat'], 'title' => 'Laporan Kunjungan RI', 'desc' => 'Tahunan / Multi-Tahun — BPJS/UMUM, ALOS, breakdown bangsal (filter exit_date)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RI']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 104, 'route' => 'manajemen.rs.rj.laporan-hak-kelas-rj', 'aksi' => ['lihat'], 'title' => 'Kunjungan RJ per Hak Kelas', 'desc' => 'Tahunan / Multi-Tahun — hak kelas rawat BPJS 1 / 2 / 3 dari data SEP, breakdown poli', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RJ']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 105, 'route' => 'manajemen.rs.ugd.laporan-hak-kelas-ugd', 'aksi' => ['lihat'], 'title' => 'Kunjungan UGD per Hak Kelas', 'desc' => 'Tahunan / Multi-Tahun — hak kelas rawat BPJS 1 / 2 / 3 dari data SEP, breakdown dokter', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'UGD']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 106, 'route' => 'manajemen.rs.ri.laporan-hak-kelas-ri', 'aksi' => ['lihat'], 'title' => 'Rawat Inap per Hak Kelas', 'desc' => 'Tahunan / Multi-Tahun — hak kelas rawat BPJS 1 / 2 / 3 + naik kelas, breakdown bangsal', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RI']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 107, 'route' => 'manajemen.rs.rj.laporan-task-id-rj', 'aksi' => ['lihat'], 'title' => 'Task ID Antrian RJ', 'desc' => 'Rekap waktu pelayanan BPJS Antrol (Task 1–7) per bulan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'BPJS']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 108, 'route' => 'manajemen.rs.ugd.laporan-task-id-ugd', 'aksi' => ['lihat'], 'title' => 'Task ID Antrian UGD', 'desc' => 'Rekap waktu Masuk UGD → Masuk Apotek → Obat Selesai per bulan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'BPJS']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 109, 'route' => 'manajemen.rs.vclaim.laporan-rujukan-keluar', 'aksi' => ['lihat'], 'title' => 'Evaluasi Rujukan Keluar', 'desc' => 'Rujukan keluar (VClaim) — poli & dokter perujuk, faskes tujuan, diagnosa rujukan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'BPJS']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 110, 'route' => 'manajemen.rs.vclaim.laporan-rujukan-masuk', 'aksi' => ['lihat'], 'title' => 'Evaluasi Rujukan Masuk', 'desc' => 'Rujukan masuk Rawat Jalan — faskes perujuk, poli penerima, diagnosa rujukan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'BPJS']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 111, 'route' => 'manajemen.rs.satu-sehat.monitoring-satu-sehat', 'aksi' => ['lihat'], 'title' => 'Monitoring Pengiriman SATUSEHAT', 'desc' => 'Kunjungan sudah/belum terkirim per bulan — capaian kelengkapan & konsistensi', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'SATUSEHAT']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 112, 'route' => 'manajemen.laporan-diagnosa.hitung-diagnosa', 'aksi' => ['lihat'], 'title' => 'Hitung Jumlah Diagnosa', 'desc' => 'Pilih ICD-10 + periode — jumlah kunjungan & pasien di RJ / UGD / RI (per gender, per sub-kode)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'DX']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 113, 'route' => 'manajemen.rs.ri.laporan-surveilans-hais', 'aksi' => ['lihat'], 'title' => 'Surveilans HAIs', 'desc' => 'Insiden rate bulanan IAD, Plebitis, ISK, VAP & ILO — kasus / hari pemakaian alat', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'PPI']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 114, 'route' => 'manajemen.rs.penunjang.ok.laporan-kasus-operasi', 'aksi' => ['lihat'], 'title' => 'Kasus Operasi (OK)', 'desc' => 'Rekap bulanan jumlah kasus per Dokter Operator per jenis operasi (BP/UM)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'OK']),
            $entry(['group' => 'Laporan Pelayanan', 'groupOrder' => 0, 'order' => 115, 'route' => 'manajemen.transfer-antar-ruangan', 'aksi' => ['lihat'], 'title' => 'Transfer Antar Ruangan', 'desc' => 'Tahunan / Multi-Tahun — status transfer stok medis & non-medis, rute Dari→Ke', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'STK']),
            $entry(['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 201, 'route' => 'manajemen.rs.penunjang.lab.laporan-permintaan-lab', 'aksi' => ['lihat'], 'title' => 'Permintaan Lab', 'desc' => 'Gabungan RJ/UGD/RI — total, BPJS/UMUM, revenue, top dokter pengirim', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'Lab']),
            $entry(['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 202, 'route' => 'manajemen.rs.penunjang.lab.laporan-pemeriksaan-lab', 'aksi' => ['lihat'], 'title' => 'Pemeriksaan Lab', 'desc' => 'Item-level — volume servis, top 20 item lab terbanyak (Hb, GDS, dst.)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'Lab']),
            $entry(['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 203, 'route' => 'manajemen.rs.penunjang.lab.laporan-pemeriksaan-dalam-luar', 'aksi' => ['lihat'], 'title' => 'Lab Internal & Rujukan', 'desc' => 'Detail per pasien + rekap — lab internal vs rujukan', 'roles' => ['admin', 'manager umum', 'supervisor penunjang'], 'badge' => 'Lab']),
            $entry(['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 204, 'route' => 'manajemen.rs.penunjang.lab.laporan-nilai-kritis', 'aksi' => ['lihat'], 'title' => 'Nilai Kritis Laboratorium', 'desc' => 'Hasil melewati ambang (Tinggi/Rendah) — perlu perhatian dokter', 'roles' => ['admin', 'manager umum', 'supervisor penunjang'], 'badge' => 'Lab']),
            $entry(['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 205, 'route' => 'manajemen.rs.penunjang.rad.laporan-permintaan-rad', 'aksi' => ['lihat'], 'title' => 'Permintaan Radiologi', 'desc' => 'Gabungan RJ/UGD/RI — total, BPJS/UMUM, revenue, top dokter pengirim', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'Rad']),
            $entry(['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 206, 'route' => 'manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad', 'aksi' => ['lihat'], 'title' => 'Pemeriksaan Radiologi', 'desc' => 'Item-level — volume servis, top 20 item radiologi terbanyak (Foto Thorax, USG, dst.)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'Rad']),
            $entry(['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 207, 'route' => 'manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad-detail', 'aksi' => ['lihat'], 'title' => 'Radiologi Detail per Pasien', 'desc' => 'Detail per pasien + rekap per unit — RJ / UGD / RI', 'roles' => ['admin', 'manager umum', 'supervisor penunjang'], 'badge' => 'Rad']),
            $entry(['group' => 'Laporan Penunjang', 'groupOrder' => 0, 'order' => 208, 'route' => 'manajemen.mutasi-obat', 'aksi' => ['lihat'], 'title' => 'Mutasi Obat', 'desc' => 'Keluar masuk obat — bulanan & tahunan, per gudang & per unit', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'OBT']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 301, 'route' => 'manajemen.sirs.ri.laporan-rl-3-2-rawat-inap', 'aksi' => ['lihat'], 'title' => 'RL 3.2 Rawat Inap', 'desc' => 'Rekap RI per jenis pelayanan, per bulan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 302, 'route' => 'manajemen.sirs.ugd.laporan-rl-3-3-rawat-darurat', 'aksi' => ['lihat'], 'title' => 'RL 3.3 Rawat Darurat', 'desc' => 'Rekap UGD/IGD per jenis pelayanan, per bulan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 303, 'route' => 'manajemen.sirs.rj.laporan-rl-3-4-pengunjung', 'aksi' => ['lihat'], 'title' => 'RL 3.4 Pengunjung', 'desc' => 'Pasien unik per bulan: Baru / Lama (lintas RJ+UGD+RI)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 304, 'route' => 'manajemen.sirs.rj.laporan-rl-3-5-kunjungan', 'aksi' => ['lihat'], 'title' => 'RL 3.5 Kunjungan', 'desc' => 'Rekap visit RJ + UGD per jenis kegiatan, dalam/luar kota', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 305, 'route' => 'manajemen.sirs.penunjang.laporan-rl-3-8-laboratorium', 'aksi' => ['lihat'], 'title' => 'RL 3.8 Laboratorium', 'desc' => 'Rekap pemeriksaan lab per jenis (gender, rata-rata/hari)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 306, 'route' => 'manajemen.sirs.penunjang.laporan-rl-3-9-radiologi', 'aksi' => ['lihat'], 'title' => 'RL 3.9 Radiologi', 'desc' => 'Rekap rad per jenis (Foto/CT/USG/MRI/Radioterapi)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor penunjang'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 307, 'route' => 'manajemen.sirs.rj.laporan-rl-3-15-kesehatan-jiwa', 'aksi' => ['lihat'], 'title' => 'RL 3.15 Kesehatan Jiwa', 'desc' => 'Tahunan — rekap kunjungan POLI PSIKIATRI per jenis kegiatan', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 308, 'route' => 'manajemen.sirs.ri.laporan-rl-3-19-cara-bayar', 'aksi' => ['lihat'], 'title' => 'RL 3.19 Cara Bayar', 'desc' => 'Tahunan — rekap pasien per cara bayar (BPJS/UMUM/Asuransi/dll)', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 309, 'route' => 'manajemen.sirs.ri.laporan-rl-4-1-morbiditas', 'aksi' => ['lihat'], 'title' => 'RL 4.1 Morbiditas RI', 'desc' => 'Tahunan — morbiditas per ICD-10 × kelompok umur × gender × hidup/mati', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 310, 'route' => 'manajemen.sirs.ri.laporan-rl-4-2-10besar', 'aksi' => ['lihat'], 'title' => 'RL 4.2 Top 10 Penyakit RI', 'desc' => 'Tahunan — 10 ICD-10 terbanyak di RI (gender + jumlah meninggal)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 311, 'route' => 'manajemen.sirs.ri.laporan-rl-4-3-10besar-mati', 'aksi' => ['lihat'], 'title' => 'RL 4.3 Top 10 Kematian RI', 'desc' => 'Tahunan — 10 ICD-10 penyebab kematian terbanyak di RI', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 312, 'route' => 'manajemen.sirs.rj.laporan-rl-5-1-morbiditas', 'aksi' => ['lihat'], 'title' => 'RL 5.1 Morbiditas RJ', 'desc' => 'Tahunan — morbiditas RJ per ICD-10 × umur × gender (kasus baru & kunjungan)', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan SIRS Online', 'groupOrder' => 0, 'order' => 313, 'route' => 'manajemen.sirs.rj.laporan-rl-5-3-10besar-kunjungan', 'aksi' => ['lihat'], 'title' => 'RL 5.3 Top 10 Kunjungan RJ', 'desc' => 'Tahunan — 10 ICD-10 dengan kunjungan terbanyak di RJ', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'RL']),
            $entry(['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 401, 'route' => 'manajemen.rs.tu.pendapatan-rs', 'aksi' => ['lihat'], 'title' => 'Pendapatan RS Keseluruhan', 'desc' => 'Total revenue RJ + UGD + RI — tahunan / multi-tahun, breakdown per modul', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU']),
            $entry(['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 402, 'route' => 'manajemen.rs.tu.pendapatan-jasa-dokter', 'aksi' => ['lihat'], 'title' => 'Pendapatan Jasa Dokter', 'desc' => 'Rekap jasa medis dokter per bulan — disetujui / belum disetujui BPJS', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU']),
            $entry(['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 403, 'route' => 'manajemen.rs.tu.monitoring-jasa-dokter', 'aksi' => ['lihat'], 'title' => 'Monitoring Jasa Dokter', 'desc' => 'Pergerakan transaksi jasa per dokter & jalur vs bulan lalu — data yang dipasok ke SIAP', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU']),
            $entry(['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 404, 'route' => 'manajemen.rs.tu.pendapatan-jasa-medis', 'aksi' => ['lihat'], 'title' => 'Pendapatan Jasa Medis', 'desc' => 'Revenue paket jasa medis per bulan — RJ / UGD / RI', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU']),
            $entry(['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 405, 'route' => 'manajemen.rs.tu.pendapatan-jasa-karyawan', 'aksi' => ['lihat'], 'title' => 'Pendapatan Jasa Karyawan', 'desc' => 'Revenue paket jasa karyawan per bulan — RJ / UGD', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU']),
            $entry(['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 406, 'route' => 'manajemen.rs.tu.laporan-operasi', 'aksi' => ['lihat'], 'title' => 'Laporan Bulanan Operasi', 'desc' => 'Tindakan kamar operasi per bulan — dokter operator, anestesi & tarif sampai ON LOOP', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'OK']),
            $entry(['group' => 'Laporan Keuangan', 'groupOrder' => 0, 'order' => 407, 'route' => 'manajemen.rs.tu.piutang-pasien', 'aksi' => ['lihat'], 'title' => 'Piutang Pasien (Cicilan / Bon)', 'desc' => 'Transaksi belum lunas (Hutang/bon) — RJ, UGD & RI, sisa piutang per pasien', 'roles' => ['admin', 'manager medis', 'manager umum'], 'badge' => 'KEU']),

            // ── Master Pelayanan ────────────────────────────────────────
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 1, 'route' => 'master.poli', 'title' => 'Master Poli', 'desc' => 'Kelola data poli & ruangan', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 2, 'route' => 'master.dokter', 'title' => 'Master Dokter', 'desc' => 'Kelola data dokter, spesialis, tarif poli/UGD/visit/konsul per kelas', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 3, 'route' => 'master.pasien', 'title' => 'Master Pasien', 'desc' => 'Kelola data pasien & rekam medis', 'roles' => [...$masterRoles, 'mr'], 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 4, 'route' => 'master.diagnosa', 'title' => 'Master Diagnosa', 'desc' => 'Kelola data diagnosa ICD-10', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 5, 'route' => 'master.radiologis', 'title' => 'Master Radiologi', 'desc' => 'Kelola data radiologi', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 6, 'route' => 'master.others', 'title' => 'Master Lain-lain', 'desc' => 'Kelola data lain-lain', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 7, 'route' => 'master.agama', 'title' => 'Master Agama', 'desc' => 'Kelola data agama pasien', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 8, 'route' => 'master.diag-keperawatan', 'title' => 'Master Diagnosis Keperawatan', 'desc' => 'Kelola data SDKI, SLKI, SIKI asuhan keperawatan', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 9, 'route' => 'master.kamar', 'title' => 'Master Kamar', 'desc' => 'Kelola bangsal, kamar & bed rawat inap', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 10, 'route' => 'master.kelas', 'title' => 'Master Kelas Rawat', 'desc' => 'Kelola kelas kamar & mapping Aplicares / SIRS', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.karyawan', 'title' => 'Master Karyawan', 'desc' => 'Kelola NIK karyawan untuk login user & coder iDRG (E-Klaim)', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 11, 'route' => 'master.karyawan-hr', 'title' => 'Master Karyawan HR', 'desc' => 'Kelola karyawan hrmst_employees: unit kerja, jabatan, status aktif & penanda petugas Kamar Operasi (crew OK)', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 12, 'route' => 'master.setup-jadwal-bpjs', 'title' => 'Pemetaan Jadwal Dokter', 'desc' => 'Ambil & terapkan jadwal praktek dokter dari BPJS ke data RS', 'roles' => [...$masterRoles, 'mr'], 'badge' => 'BPJS']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 13, 'route' => 'master.stocklocations', 'title' => 'Master Lokasi Stok', 'desc' => 'Kelola kode lokasi stok (gudang, apotek, ruangan, klinik) untuk transfer obat & barang', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 17, 'route' => 'master.ews', 'title' => 'Master EWS', 'desc' => 'Parameter, rentang skor & respon Early Warning System (Dewasa / Anak / Neonatus / MEOWS) untuk Observasi Lanjutan', 'roles' => $masterRoles, 'badge' => 'EMR']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 18, 'route' => 'master.automatic-stop-order', 'title' => 'Master Automatic Stop Order', 'desc' => 'Automatic Stop Order: golongan obat, batas hari order berhenti otomatis, dan pemetaan obat ke golongan', 'roles' => $masterRoles, 'badge' => 'Apotek']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 16, 'route' => 'master.identitas', 'title' => 'Master Identitas RS', 'desc' => 'Kop cetakan (nama, alamat, telepon) + setelan PPN & harga pokok penerimaan', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 14, 'route' => 'master.jasa-medis', 'title' => 'Master Jasa Medis', 'desc' => 'Kelola tarif jasa medis (Umum & BPJS) + paket bundling obat / lain-lain', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),
            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 15, 'route' => 'master.jasa-dokter', 'title' => 'Master Jasa Dokter', 'desc' => 'Kelola tarif jasa dokter (Umum & BPJS) + paket bundling obat / lain-lain', 'roles' => $masterRoles, 'badge' => 'Pelayanan']),

            $entry(['group' => 'Master Pelayanan', 'groupOrder' => 1, 'order' => 19, 'route' => 'master.mapping-satusehat', 'title' => 'Mapping SATUSEHAT', 'desc' => 'Cakupan KFA, LOINC, IHS dan Location pada master, dengan tautan ke yang belum terisi', 'roles' => ['admin', 'apoteker', 'laboratorium', 'radiologi', 'mr'], 'badge' => 'SATUSEHAT']),

            // ── Master Laboratorium ──────────────────────────────────
            $entry(['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 1, 'route' => 'master.laborat', 'title' => 'Master Laboratorium', 'desc' => 'Kelola kategori lab & item pemeriksaan', 'roles' => $masterRoles, 'badge' => 'Lab']),
            $entry(['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 2, 'route' => 'master.loinc', 'title' => 'Master LOINC', 'desc' => 'Kode LOINC pemeriksaan lab & observasi — LOV + pengiriman SATUSEHAT', 'roles' => $masterRoles, 'badge' => 'SATUSEHAT']),
            $entry(['group' => 'Master Laboratorium', 'groupOrder' => 2, 'order' => 3, 'route' => 'master.snomed', 'title' => 'Master SNOMED CT', 'desc' => 'Kode SNOMED CT keluhan / prosedur / alergi — LOV + pengiriman SATUSEHAT', 'roles' => $masterRoles, 'badge' => 'SATUSEHAT']),

            // ── Master Apotek ────────────────────────────────────────
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 1, 'route' => 'master.obat', 'title' => 'Master Obat', 'desc' => 'Kelola data obat & farmasi', 'roles' => $masterRoles, 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 2, 'route' => 'master.obat-kronis', 'title' => 'Master Obat Kronis', 'desc' => 'Daftar obat kronis BPJS — max qty per resep & tarif klaim', 'roles' => $masterRoles, 'badge' => 'Kronis']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 3, 'route' => 'master.signa-catatan', 'title' => 'Master Catatan Khusus Signa', 'desc' => 'LOV catatan khusus signa untuk e-resep (RJ/UGD/RI)', 'roles' => $masterRoles, 'badge' => 'Apotek']),
            $entry(['group' => 'Master Apotek', 'groupOrder' => 3, 'order' => 4, 'route' => 'master.interaksi-obat', 'title' => 'Master Interaksi Obat', 'desc' => 'Kelola kelompok interaksi antar obat & produk anggotanya', 'roles' => [...$masterRoles, 'apoteker'], 'badge' => 'Apotek']),

            // ── Master Akuntansi ──────────────────────────────────────
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 1, 'route' => 'master.group-akun', 'title' => 'Master Group Akun', 'desc' => 'Kelola group akun (Aktiva, Kewajiban, Modal, Pendapatan, Beban)', 'roles' => $masterRoles, 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 2, 'route' => 'master.akun', 'title' => 'Master Akun', 'desc' => 'Kelola COA (Chart of Account) — daftar akun & saldo awal', 'roles' => $masterRoles, 'badge' => 'Akuntansi']),
            $entry(['group' => 'Master Akuntansi', 'groupOrder' => 4, 'order' => 3, 'route' => 'master.konf-akun-trans', 'title' => 'Master Konfigurasi Akun Transaksi', 'desc' => 'Mapping akun debit/kredit untuk tiap jenis transaksi (RJ, UGD, RI, Resep, dll.)', 'roles' => $masterRoles, 'badge' => 'Akuntansi']),

            // ── Rawat Jalan ────────────────────────────────────────────
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 1, 'route' => 'rj.daftar', 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat', 'supervisor tu', 'tu']], 'bukaDiagnosa' => ['Buka Diagnosa', ['admin', 'casemix', 'dokter', 'mr']], 'bukaRiwayatKontrol' => ['Riwayat Kontrol', ['admin', 'casemix', 'mr', 'tu']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => self::ROLES_PENDAFTARAN, 'ubah' => self::ROLES_PENDAFTARAN], 'title' => 'Daftar Rawat Jalan', 'desc' => 'Pendaftaran & manajemen pasien poli rawat jalan harian', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'mr'], 'badge' => 'RJ']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 2, 'route' => 'rj.pelayanan', 'aksi' => ['lihat'], 'khusus' => ['bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaPengkajianMedis' => ['Pengkajian Medis', ['admin', 'dokter', 'mr', 'perawat']], 'bukaPrmrj' => ['PRMRJ', ['admin', 'dokter', 'mr', 'perawat']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'perawat', 'tu']], 'bukaTransferUgd' => ['Transfer ke UGD', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Pelayanan Rawat Jalan', 'desc' => 'EMR poli — anamnesis, pemeriksaan, diagnosa & resep oleh dokter/perawat', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat'], 'badge' => 'PEL-RJ']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 3, 'route' => 'rj.booking', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'title' => 'Booking Rawat Jalan', 'desc' => 'Pasien booking poli via Mobile JKN — siap diaktivasi jadi pendaftaran', 'roles' => ['admin', 'mr', 'manager umum', 'supervisor tu'], 'badge' => 'BKG']),
            $entry(['group' => 'Rawat Jalan', 'groupOrder' => 5, 'order' => 4, 'route' => 'kontrol.jadwal-kontrol', 'aksi' => ['lihat'], 'title' => 'Jadwal Kontrol Pasien', 'desc' => 'Lihat & geser tanggal rencana kontrol (SKDP) RJ/RI — update otomatis ke BPJS', 'roles' => ['admin', 'mr', 'tu', 'supervisor tu', 'manager umum'], 'badge' => 'SKDP']),

            // ── UGD ────────────────────────────────────────────────────
            $entry(['group' => 'Unit Gawat Darurat', 'groupOrder' => 6, 'order' => 1, 'route' => 'ugd.daftar', 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat', 'supervisor tu', 'tu']], 'bukaDiagnosa' => ['Buka Diagnosa', ['admin', 'casemix', 'dokter', 'mr']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'mr', 'tu']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => self::ROLES_PENDAFTARAN, 'ubah' => self::ROLES_PENDAFTARAN], 'title' => 'Daftar UGD', 'desc' => 'Pendaftaran & manajemen pasien Unit Gawat Darurat harian', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'mr'], 'badge' => 'UGD']),
            $entry(['group' => 'Unit Gawat Darurat', 'groupOrder' => 6, 'order' => 2, 'route' => 'ugd.pelayanan', 'aksi' => ['lihat'], 'khusus' => ['bukaCekPesertaBpjs' => ['Cek Peserta BPJS', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'apoteker', 'casemix', 'dokter', 'laboratorium', 'mr', 'perawat']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'perawat', 'tu']], 'bukaTransferRi' => ['Transfer ke RI', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Pelayanan UGD', 'desc' => 'EMR UGD — triase, asesmen, tindakan & resep oleh dokter/perawat UGD', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat', 'apoteker', 'laboratorium'], 'badge' => 'PEL-UGD']),

            // ── Rawat Inap ─────────────────────────────────────────────
            $entry(['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 1, 'route' => 'ri.daftar', 'khusus' => ['bukaRekamMedis' => ['Buka Rekam Medis', ['admin', 'apoteker', 'casemix', 'dokter', 'gizi', 'laboratorium', 'perawat', 'radiologi']], 'bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'gizi', 'laboratorium', 'mr', 'perawat', 'radiologi', 'supervisor tu', 'tu']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'gizi', 'perawat', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'mr', 'tu']]], 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => self::ROLES_PENDAFTARAN, 'ubah' => self::ROLES_PENDAFTARAN], 'title' => 'Daftar Rawat Inap', 'desc' => 'Pendaftaran, transfer kamar, & manajemen pasien rawat inap', 'roles' => ['admin', 'manager medis', 'manager umum', 'supervisor tu', 'mr', 'perawat', 'dokter', 'casemix', 'tu', 'apoteker', 'gizi', 'laboratorium', 'radiologi'], 'badge' => 'RI']),
            $entry(['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 2, 'route' => 'ri.update-tt', 'aksi' => ['lihat'], 'khusus' => ['kirim' => ['Kirim ke Aplicares BPJS & SIRS', ['admin', 'mr', 'perawat', 'dokter']]], 'title' => 'Sinkronisasi Tempat Tidur', 'desc' => 'Sync ketersediaan TT rawat inap → Aplicares BPJS & SIRS Kemenkes', 'roles' => ['admin', 'mr', 'perawat', 'dokter'], 'badge' => 'TT']),
            $entry(['group' => 'Rawat Inap', 'groupOrder' => 7, 'order' => 3, 'route' => 'ri.gizi', 'aksi' => ['lihat'], 'khusus' => ['bukaModulDokumen' => ['Buka Modul Dokumen', ['admin', 'casemix', 'dokter', 'gizi', 'mr', 'perawat']], 'bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'gizi', 'perawat', 'tu']], 'bukaModulDokumenEntri' => ['Buka Modul Dokumen (panel entri)', ['admin', 'apoteker', 'casemix', 'dokter', 'gizi', 'perawat']], 'bukaAdministrasiEntri' => ['Buka Administrasi (panel entri)', ['admin', 'apoteker', 'casemix', 'gizi', 'perawat']]], 'title' => 'Gizi Rawat Inap', 'desc' => 'Program diet harian pasien RI & rekap porsi dapur', 'roles' => ['admin', 'manager medis', 'manager umum', 'gizi'], 'badge' => 'GIZI']),

            // ── Casemix ──────────────────────────────────────
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 1, 'route' => 'casemix', 'aksi' => ['lihat'], 'title' => 'Casemix', 'desc' => 'Daftar pasien bulanan — tab RJ, UGD, RI dalam 1 halaman', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'CSX']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 2, 'route' => 'rj.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan RJ', 'desc' => 'List pasien rawat jalan per bulan (mm/yyyy)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'RJ-BLN']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 3, 'route' => 'ugd.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan UGD', 'desc' => 'List pasien UGD per bulan (mm/yyyy)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'UGD-BLN']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 4, 'route' => 'ri.daftar-bulanan', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'casemix', 'tu']], 'bukaBerkasBpjs' => ['Buka Berkas BPJS', ['admin', 'casemix', 'tu']]], 'title' => 'Daftar Pasien Bulanan RI', 'desc' => 'List pasien RI per bulan berdasarkan tgl pulang', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'RI-BLN']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 8, 'route' => 'ri.kontrol-klaim', 'aksi' => ['lihat'], 'title' => 'Kontrol Klaim Rawat Inap', 'desc' => 'Pantau klaim pasien yang sedang dirawat inap', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'KK-RI']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 7, 'route' => 'manajemen.rs.rekam-medis.arsip-modul-dokumen', 'title' => 'Arsip Modul Dokumen', 'desc' => 'Kunjungan RJ/UGD/RI per bulan — buka Rekam Medis untuk lihat & cetak modul dokumen', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'Casemix']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 5, 'route' => 'casemix', 'aksi' => ['lihat'], 'params' => ['tab' => 'rekap-rj'], 'title' => 'Rekap iDRG Rawat Jalan', 'desc' => 'Rekap klaim iDRG/INA-CBG pasien rawat jalan per SEP (get_claim_data)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'IDRG-RJ']),
            $entry(['group' => 'Casemix', 'groupOrder' => 8, 'order' => 6, 'route' => 'casemix', 'aksi' => ['lihat'], 'params' => ['tab' => 'rekap'], 'title' => 'Rekap iDRG Rawat Inap', 'desc' => 'Rekap klaim iDRG/INA-CBG pasien rawat inap per SEP (get_claim_data)', 'roles' => ['admin', 'manager umum', 'casemix', 'tu'], 'badge' => 'IDRG-RI']),

            // ── DINKESTA (Dinas Kesehatan Tulungagung — kewajiban daerah) ──
            $entry(['group' => 'Dinas Kesehatan Tulungagung', 'groupOrder' => 8, 'order' => 1, 'route' => 'dinkesta.kirim-rj', 'aksi' => ['lihat'], 'khusus' => ['kirim' => ['Kirim ke Portal DINKESTA', ['admin', 'casemix', 'tu']]], 'title' => 'Kirim DINKESTA Rawat Jalan', 'desc' => 'Kirim bulk diagnosis ICD-10 kunjungan RJ per bulan ke Portal Satu Data Kesehatan Tulungagung', 'roles' => ['admin', 'casemix', 'tu'], 'badge' => 'DINKES']),

            // ── Apotek (transaksi) ──────────────────────────────────
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 1, 'route' => 'apotek', 'aksi' => ['lihat'], 'title' => 'Antrian Apotek', 'desc' => 'Telaah resep & pelayanan kefarmasian — tab RJ, UGD, RI', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT']),
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 2, 'route' => 'rj.antrian-apotek', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'casemix', 'perawat']]], 'title' => 'Antrian Apotek RJ', 'desc' => 'Antrian apotek khusus pasien rawat jalan', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-RJ']),
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 3, 'route' => 'ugd.antrian-apotek', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'casemix', 'perawat']]], 'title' => 'Antrian Apotek UGD', 'desc' => 'Antrian apotek khusus pasien UGD', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-UGD']),
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 4, 'route' => 'ri-resep.antrian', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'apoteker', 'tu']]], 'title' => 'Antrian Apotek RI', 'desc' => 'Telaah resep & pelayanan kefarmasian rawat inap', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'APT-RI']),
            $entry(['group' => 'Apotek', 'groupOrder' => 9, 'order' => 5, 'route' => 'ri.pto', 'aksi' => ['lihat'], 'title' => 'Pemantauan Terapi Obat (PTO) Rawat Inap', 'desc' => 'Pantau seluruh terapi obat pasien rawat inap dari e-resep — khusus apoteker', 'roles' => ['admin', 'apoteker', 'manager medis'], 'badge' => 'PTO']),

            // ── Kasir ─────────────────────────────────────────────────
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 1, 'route' => 'kasir', 'aksi' => ['lihat'], 'title' => 'Antrian Kasir', 'desc' => 'Kasir — tab RJ, UGD, RI', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR']),
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 2, 'route' => 'rj.antrian-kasir', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']], 'bukaTransferUgd' => ['Transfer ke UGD', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Antrian Kasir RJ', 'desc' => 'Antrian kasir khusus pasien rawat jalan', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-RJ']),
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 3, 'route' => 'ugd.antrian-kasir', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']], 'bukaTransferRi' => ['Transfer ke RI', ['admin', 'manager umum', 'perawat', 'supervisor tu', 'tu']]], 'title' => 'Antrian Kasir UGD', 'desc' => 'Antrian kasir khusus pasien UGD', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-UGD']),
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 4, 'route' => 'kasir.antrian-ri', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']]], 'title' => 'Resep Inap', 'desc' => 'Kasir resep rawat inap (per resep)', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-RI']),
            $entry(['group' => 'Kasir', 'groupOrder' => 9, 'order' => 5, 'route' => 'kasir.daftar-ri', 'aksi' => ['lihat'], 'khusus' => ['bukaAdministrasi' => ['Buka Administrasi', ['admin', 'manager umum', 'supervisor tu', 'tu']]], 'title' => 'Pasien Rawat Inap', 'desc' => 'List pasien rawat inap untuk administrasi & pembayaran', 'roles' => ['admin', 'tu', 'manager umum', 'supervisor tu'], 'badge' => 'KSR-DAFTAR']),

            // ── Penunjang ──────────────────────────────────────────────
            $entry(['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 1, 'route' => 'penunjang.laborat', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['prosesStatus' => ['Proses / ubah status pemeriksaan', ['admin', 'laboratorium']], 'batal' => ['Batal pendaftaran / transaksi lab', ['admin', 'supervisor penunjang']]], 'title' => 'Transaksi Laboratorium', 'desc' => 'Input hasil pemeriksaan laboratorium pasien', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'laboratorium'], 'badge' => 'LAB']),
            $entry(['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 2, 'route' => 'penunjang.laborat.lab-luar', 'aksi' => ['lihat', 'ubah'], 'title' => 'Upload Hasil Lab Luar', 'desc' => 'Upload PDF hasil lab luar yang sudah Selesai', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'laboratorium'], 'badge' => 'LAB-LUAR']),
            $entry(['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 3, 'route' => 'penunjang.radiologi.upload', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['batal' => ['Batal order radiologi', ['admin', 'supervisor penunjang']]], 'title' => 'Upload Hasil Radiologi', 'desc' => 'Upload foto radiologi & hasil bacaan PDF', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'radiologi'], 'badge' => 'RAD']),
            $entry(['group' => 'Penunjang', 'groupOrder' => 10, 'order' => 4, 'route' => 'penunjang.kamar-operasi', 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => self::ROLES_TRANSAKSI_OK, 'ubah' => self::ROLES_TRANSAKSI_OK], 'khusus' => ['batal' => ['Batal transaksi operasi', ['admin', 'supervisor penunjang', 'supervisor tu']], 'bukaDokumenBedah' => ['Menu titik-3 & Dokumen Pelayanan Bedah', ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'perawat', 'dokter', 'casemix', 'mr', 'gizi']]], 'title' => 'Transaksi Tindakan Operasi', 'desc' => 'Tindakan operasi, tarif, & transfer biaya ke rawat inap — juga ada di grup Operasi', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'dokter', 'perawat'], 'badge' => 'OK']),

            // ── Gudang ────────────────────────────────────────────────
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 1, 'route' => 'gudang.penerimaan-medis', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'rolesAksi' => ['hapus' => ['admin', 'tu']], 'title' => 'Obat dari PBF', 'desc' => 'Penerimaan obat dari PBF / Supplier (Gudang Medis)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'RCV']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 2, 'route' => 'gudang.penerimaan-non-medis', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'rolesAksi' => ['hapus' => ['admin', 'tu']], 'title' => 'Barang dari Supplier (Non-Medis)', 'desc' => 'Penerimaan barang non-medis dari supplier', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'RCV-N']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 3, 'route' => 'gudang.transfer-stock', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['posting' => ['Posting Transfer', ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker']]], 'title' => 'Transfer Stok Medis', 'desc' => 'Pindahkan obat antar lokasi — sumber dari Gudang Medis atau Apotek', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'TRF']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 4, 'route' => 'gudang.transfer-stock-non', 'aksi' => ['lihat', 'tambah', 'ubah'], 'khusus' => ['posting' => ['Posting Transfer', ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu']]], 'title' => 'Transfer Stok Non-Medis', 'desc' => 'Pindahkan barang non-medis (ATK, RT, dll.) dari Gudang Non-Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'TRF-N']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 5, 'route' => 'gudang.kartu-stock', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Gudang Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'STK']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 6, 'route' => 'gudang.kartu-stock-apt', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Apotek', 'desc' => 'Riwayat mutasi stok per produk — Apotek', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang obat', 'apoteker'], 'badge' => 'STK-A']),
            $entry(['group' => 'Gudang', 'groupOrder' => 11, 'order' => 7, 'route' => 'gudang.kartu-stock-non', 'aksi' => ['lihat'], 'title' => 'Kartu Stock — Non-Medis', 'desc' => 'Riwayat mutasi stok per produk — Gudang Non-Medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'gudang non medis', 'tu'], 'badge' => 'STK-N']),

            // ── Keuangan ──────────────────────────────────────────────
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 1, 'route' => 'keuangan.penerimaan-kas-tu', 'aksi' => ['lihat', 'tambah'], 'title' => 'Penerimaan Kas TU', 'desc' => 'Catat penerimaan kas di luar transaksi pelayanan RS', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'CI']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 2, 'route' => 'keuangan.pengeluaran-kas-tu', 'aksi' => ['lihat', 'tambah'], 'title' => 'Pengeluaran Kas TU', 'desc' => 'Catat pengeluaran kas di luar transaksi pelayanan RS', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'CO']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 3, 'route' => 'keuangan.pembayaran-piutang-bpjs', 'aksi' => ['lihat'], 'title' => 'Pelunasan Klaim BPJS & Kronis', 'desc' => 'Lunasi tagihan belum lunas per bulan — tab BPJS RJ/UGD/RI & Obat Kronis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'BPJS']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 4, 'route' => 'keuangan.pembayaran-piutang-pasien', 'aksi' => ['lihat'], 'title' => 'Pembayaran Piutang Per Pasien', 'desc' => 'Pelunasan / angsuran nota pasien (umum) — alokasi FIFO', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'PIUT']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 5, 'route' => 'keuangan.pembayaran-hutang-pbf', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => self::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Pembayaran Hutang PBF', 'desc' => 'Pelunasan / angsuran hutang ke supplier obat (PBF)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'HTG']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 6, 'route' => 'keuangan.pembayaran-hutang-non-medis', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => self::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Pembayaran Hutang Non-Medis', 'desc' => 'Pelunasan / angsuran hutang ke supplier non-medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'HTG-N']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 7, 'route' => 'keuangan.topup-supplier-pbf', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => self::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Setor DP Supplier PBF', 'desc' => 'Setor uang muka / DP ke supplier obat (PBF)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'DP']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 8, 'route' => 'keuangan.topup-supplier-non-medis', 'aksi' => ['lihat', 'tambah', 'hapus'], 'rolesAksi' => ['hapus' => self::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Setor DP Supplier Non-Medis', 'desc' => 'Setor uang muka / DP ke supplier non-medis', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'DP-N']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 15, 'route' => 'keuangan.kartu-dp-supplier', 'aksi' => ['lihat', 'ubah'], 'title' => 'Kartu DP Supplier', 'desc' => 'Saldo awal, setor, pemakaian, opname & saldo berjalan uang muka per supplier (PBF / Non-Medis)', 'roles' => ['admin'], 'badge' => 'K-DP']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 14, 'route' => 'keuangan.tutup-buku', 'aksi' => ['lihat', 'ubah'], 'title' => 'Tutup Buku Tahunan', 'desc' => 'Saldo awal tahun berikutnya: akun (Kas & Neraca), stok, hutang & DP supplier', 'roles' => ['admin'], 'badge' => 'TB']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 9, 'route' => 'keuangan.saldo-kas', 'aksi' => ['lihat', 'ubah'], 'rolesAksi' => ['ubah' => self::ROLES_PENGAWAS_KEUANGAN], 'title' => 'Saldo Kas', 'desc' => 'Posisi saldo kas/bank per tanggal — riwayat transaksi & edit saldo awal tahun (admin)', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'SK']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 10, 'route' => 'keuangan.buku-besar', 'aksi' => ['lihat'], 'title' => 'Buku Besar', 'desc' => 'Mutasi & saldo per akun dalam periode tertentu', 'roles' => ['admin', 'manager umum'], 'badge' => 'BB']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 11, 'route' => 'keuangan.laba-rugi', 'aksi' => ['lihat'], 'title' => 'Laporan Laba Rugi', 'desc' => 'Pendapatan vs beban — laba/rugi periode berjalan', 'roles' => ['admin', 'manager umum'], 'badge' => 'LR']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 12, 'route' => 'keuangan.neraca', 'aksi' => ['lihat'], 'title' => 'Laporan Neraca', 'desc' => 'Posisi aktiva, kewajiban & modal per tanggal', 'roles' => ['admin', 'manager umum'], 'badge' => 'NRC']),
            $entry(['group' => 'Keuangan', 'groupOrder' => 12, 'order' => 13, 'route' => 'keuangan.analisa-perputaran-obat', 'aksi' => ['lihat'], 'title' => 'Analisa Perputaran Obat', 'desc' => 'Fast / slow / dead moving per lokasi — sorot modal stok yang mengendap', 'roles' => ['admin', 'manager umum', 'supervisor tu', 'tu'], 'badge' => 'STOK']),

            // ── Operasi ────────────────────────────────────────────────
            $entry(['group' => 'Operasi', 'groupOrder' => 13, 'order' => 1, 'route' => 'operasi.jadwal-operasi', 'aksi' => ['lihat', 'tambah', 'ubah', 'hapus'], 'title' => 'Jadwal Operasi', 'desc' => 'Booking & manajemen jadwal operasi pasien', 'roles' => ['admin', 'manager medis', 'dokter', 'perawat'], 'badge' => 'JDW']),
            $entry(['group' => 'Operasi', 'groupOrder' => 13, 'order' => 2, 'route' => 'penunjang.kamar-operasi', 'aksi' => ['lihat', 'tambah', 'ubah'], 'rolesAksi' => ['tambah' => self::ROLES_TRANSAKSI_OK, 'ubah' => self::ROLES_TRANSAKSI_OK], 'khusus' => ['batal' => ['Batal transaksi operasi', ['admin', 'supervisor penunjang', 'supervisor tu']], 'bukaDokumenBedah' => ['Menu titik-3 & Dokumen Pelayanan Bedah', ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'perawat', 'dokter', 'casemix', 'mr', 'gizi']]], 'title' => 'Transaksi Tindakan Operasi', 'desc' => 'Tindakan operasi, tarif, & transfer biaya ke rawat inap', 'roles' => ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'dokter', 'perawat'], 'badge' => 'OK']),

            // ── Down Time ─────────────────────────────────────────────
            // Terbuka untuk SEMUA role: saat SIMRS bermasalah, siapa pun yang masih
            // bisa membuka aplikasi harus dapat mengunduh formulir cadangannya.
            $entry(['group' => 'Down Time', 'groupOrder' => 14, 'order' => 1, 'route' => 'downtime.formulir-manual', 'aksi' => ['lihat'], 'title' => 'Formulir Manual Down Time', 'desc' => 'Formulir cadangan saat SIMRS tidak dapat diakses — EMR, administrasi, kasir & apotek RJ/UGD/RI + log & rekonsiliasi IT', 'roles' => [self::SEMUA_ROLE], 'badge' => 'DT']),
            $entry(['group' => 'Down Time', 'groupOrder' => 14, 'order' => 2, 'route' => 'downtime.daftar-tarif', 'aksi' => ['lihat'], 'title' => 'Daftar Tarif Down Time', 'desc' => 'Price list acuan pengisian nominal formulir manual — kamar, visite & konsul, jasa medis/dokter/karyawan, lab, radiologi, obat & lain-lain', 'roles' => [self::SEMUA_ROLE], 'badge' => 'TRF']),

            $entry(['group' => 'Down Time', 'groupOrder' => 14, 'order' => 3, 'route' => 'downtime.pelaporan', 'aksi' => ['lihat', 'tambah', 'ubah'], 'title' => 'Pelaporan Down Time SIMRS', 'desc' => 'Rekam formulir DT-01 sesudah layanan pulih — kejadian, penanganan, dampak & evaluasi', 'roles' => ['admin', 'manager umum'], 'badge' => 'DT-01']),

            // ── Sistem ────────────────────────────────────────────────
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 1, 'route' => 'database-monitor.monitoring-dashboard', 'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session', 'roles' => ['admin'], 'badge' => 'DB']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 2, 'route' => 'database-monitor.monitoring-mount-control', 'title' => 'Mounting Control', 'desc' => 'Mount/unmount share folder jaringan (CIFS/SMB)', 'roles' => ['admin', 'perawat'], 'badge' => 'MNT']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 3, 'route' => 'database-monitor.user-control', 'title' => 'User Control', 'desc' => 'Kelola user & hak akses sistem', 'roles' => ['admin'], 'badge' => 'USR']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 4, 'route' => 'database-monitor.role-control', 'title' => 'Role Control', 'desc' => 'Kelola role & permission sistem', 'roles' => ['admin'], 'badge' => 'ROL']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 5, 'route' => 'database-monitor.user-online', 'title' => 'User Online', 'desc' => 'Daftar user yang sedang aktif login (last_seen_at < threshold menit)', 'roles' => ['admin'], 'badge' => 'ONL']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 6, 'route' => 'database-monitor.log-bpjs', 'title' => 'Log BPJS / E-Klaim API', 'desc' => 'Riwayat pemanggilan V-Claim, Antrean, Aplicares, I-Care, SIRS, iDRG/INACBG', 'roles' => ['admin'], 'badge' => 'LOG']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 18, 'route' => 'database-monitor.monitor-server', 'title' => 'Monitor Server', 'desc' => 'Worker PHP-FPM, timeout Apache (504), slowlog & error Laravel', 'roles' => ['admin'], 'badge' => 'FPM']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 7, 'route' => 'sistem.pemantauan-suhu-ruang-server', 'title' => 'Pemantauan Suhu Ruang Server', 'desc' => 'Formulir MRMIK 2.2 — suhu & status AC ruang server per bulan', 'roles' => ['admin', 'manager umum'], 'badge' => 'SRV']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 8, 'route' => 'sistem.pemantauan-akses-ruang-server', 'title' => 'Pemantauan Akses Ruang Server', 'desc' => 'Formulir MRMIK 2.2 — siapa keluar-masuk ruang server, kapan & untuk apa', 'roles' => ['admin', 'manager umum'], 'badge' => 'AKS']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 9, 'route' => 'panduan-dev', 'title' => 'Standarisasi UI', 'desc' => 'Acuan design system internal — warna, tipografi & komponen', 'roles' => ['admin'], 'badge' => 'UI']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 10, 'route' => 'panduan-dev.koding-master', 'title' => 'Tutorial Koding Master', 'desc' => 'Panduan membangun modul master (2-file, event, LOV, CRUD)', 'roles' => ['admin'], 'badge' => 'DOC']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 11, 'route' => 'panduan-dev.koding-transaksi', 'title' => 'Tutorial Koding Transaksi', 'desc' => 'Pola modul transaksi RJ/UGD/RI — JSON CLOB, EMR, dokumen, administrasi', 'roles' => ['admin'], 'badge' => 'DOC']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 12, 'route' => 'panduan-dev.koding-satusehat', 'title' => 'Tutorial Koding SATUSEHAT', 'desc' => 'Kirim data FHIR R4 ke SATUSEHAT — resource, kode, checklist kolom wajib', 'roles' => ['admin'], 'badge' => 'DOC']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 13, 'route' => 'panduan-dev.koding-administrasi', 'title' => 'Tutorial Koding Administrasi', 'desc' => 'Konsep administrasi/kasir RJ/UGD/RI sampai pulang + transfer & model batal', 'roles' => ['admin'], 'badge' => 'DOC']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 14, 'route' => 'panduan-dev.alur-pelayanan', 'title' => 'Tutorial Alur Pelayanan', 'desc' => 'Alur pasien pendaftaran → EMR → apotek → kasir per jalur RJ/UGD/RI', 'roles' => ['admin'], 'badge' => 'DOC']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 15, 'route' => 'panduan-dev.koding-struktur', 'title' => 'Standar Struktur Folder', 'desc' => 'Di mana berkas ditaruh & dinamai — prefix ⚡, suffix peran/jalur, Trait vs Support, prefix URL', 'roles' => ['admin'], 'badge' => 'DOC']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 16, 'route' => 'panduan-dev.koding-skill', 'title' => 'Katalog Skill', 'desc' => 'Daftar skill repo (.claude/skills/*) + kapan dibaca', 'roles' => ['admin'], 'badge' => 'DOC']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 17, 'route' => 'panduan-dev.mobile-jkn', 'title' => 'Panduan Mobile JKN', 'desc' => 'Cara kerja API Antrean Online BPJS — booking, checkin, kuota, host API, penanganan masalah', 'roles' => ['admin'], 'badge' => 'DOC']),
            $entry(['group' => 'Sistem', 'groupOrder' => 15, 'order' => 18, 'route' => 'panduan-dev.siap-api', 'title' => 'Panduan API SIAP', 'desc' => 'Bridging penggajian ke SIAP (SIRUS sebagai server) — akun & JWT, endpoint gaji dokter, pengaman, setup, uji', 'roles' => ['admin'], 'badge' => 'DOC']),
        ];
    }
}
