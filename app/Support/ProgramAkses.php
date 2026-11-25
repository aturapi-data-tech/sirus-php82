<?php

namespace App\Support;

use RuntimeException;

/**
 * SUMBER TUNGGAL definisi hak akses — SATU BLOK PER PROGRAM.
 *
 * Satu program (mis. "Pelayanan Rawat Jalan", "Administrasi & Kasir Rawat Jalan") = SATU BERKAS,
 * dikelompokkan per folder di app/Akses/Program/<Kelompok>/:
 *
 *   RawatJalan/_kelompok.php            → ['judul' => 'Rawat Jalan', 'program' => ['PelayananRawatJalan', ...]]
 *   RawatJalan/PelayananRawatJalan.php  → [
 *       'nama'       => 'Pelayanan Rawat Jalan',                              // judul di Role Control
 *       'menu'       => [ [entri menu: route, aksi, khusus, roles, ...] ],   // → AppMenu
 *       'aksiKhusus' => [ 'rj.detailKunjungan' => ['label' => ..., 'roles' => ...] ],  // → Gate
 *       'pintu'      => [ 'emr-rj.rekam-medis.open' => [hak pembuka...] ],   // → listener modal
 *   ]
 *
 * AppMenu::all(), AksiRole::khusus() dan AksesHalaman::pintu() membaca dari sini, dan Role
 * Control menampilkan matriks per kelompok → program dengan urutan yang sama.
 *
 * Menambah aksi: tulis di berkas programnya. Program baru: buat berkasnya + daftarkan namanya di
 * 'program' _kelompok.php (urutan tampil). Pasang @can + #[Authorize] di komponen, deploy —
 * permission + centang awal dibuat otomatis (SinkronAksesOtomatis).
 */
class ProgramAkses
{
    /** Urutan kelompok = urutan tampil di Role Control. Nama folder di app/Akses/Program. */
    private const BERKAS = [
        'LaporanManajemen',
        'Master',
        'RawatJalan',
        'Ugd',
        'RawatInap',
        'Casemix',
        'Penunjang',
        'Gudang',
        'Keuangan',
        'Sistem',
        'Bersama',
    ];

    /** @var array<int, array{judul: string, program: array<string, array>}>|null */
    private static ?array $kelompok = null;

    private static ?array $aksiKhusus = null;

    private static ?array $pintu = null;

    /**
     * Semua kelompok beserta programnya, urut sesuai BERKAS.
     *
     * @return array<int, array{judul: string, program: array<string, array{menu?: array, aksiKhusus?: array, pintu?: array}>}>
     */
    public static function kelompok(): array
    {
        if (self::$kelompok !== null) {
            return self::$kelompok;
        }

        $kelompok = [];
        foreach (self::BERKAS as $folder) {
            $kepala = require app_path("Akses/Program/{$folder}/_kelompok.php");
            if (!is_array($kepala) || !isset($kepala['judul'], $kepala['program']) || !is_array($kepala['program'])) {
                throw new RuntimeException("Akses/Program/{$folder}/_kelompok.php tidak berisi 'judul' & daftar 'program'.");
            }

            $program = [];
            foreach ($kepala['program'] as $berkas) {
                $isi = require app_path("Akses/Program/{$folder}/{$berkas}.php");
                if (!is_array($isi) || !is_string($isi['nama'] ?? null)) {
                    throw new RuntimeException("Akses/Program/{$folder}/{$berkas}.php tidak berisi 'nama' program.");
                }
                if (isset($program[$isi['nama']])) {
                    throw new RuntimeException("Program '{$isi['nama']}' dua kali di kelompok {$kepala['judul']}.");
                }
                $nama = $isi['nama'];
                unset($isi['nama']);
                $program[$nama] = $isi;
            }

            $kelompok[] = ['judul' => $kepala['judul'], 'program' => $program];
        }

        return self::$kelompok = $kelompok;
    }

    /**
     * Entri menu semua program (format AppMenu), dalam urutan definisi.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function menu(): array
    {
        $menu = [];
        foreach (self::kelompok() as $kelompok) {
            foreach ($kelompok['program'] as $program) {
                foreach ($program['menu'] ?? [] as $entri) {
                    $menu[] = $entri;
                }
            }
        }

        return $menu;
    }

    /**
     * Katalog aksi khusus: nama Gate => [label, roles, program, kelompok].
     *
     * @return array<string, array{label: string, roles: array<int, string>, program: string, kelompok: string}>
     */
    public static function aksiKhusus(): array
    {
        if (self::$aksiKhusus !== null) {
            return self::$aksiKhusus;
        }

        $aksiKhusus = [];
        foreach (self::kelompok() as $kelompok) {
            foreach ($kelompok['program'] as $namaProgram => $program) {
                foreach ($program['aksiKhusus'] ?? [] as $nama => $aksi) {
                    if (isset($aksiKhusus[$nama])) {
                        throw new RuntimeException("Aksi khusus '{$nama}' didefinisikan dua kali.");
                    }
                    $aksiKhusus[$nama] = $aksi + ['program' => $namaProgram, 'kelompok' => $kelompok['judul']];
                }
            }
        }

        return self::$aksiKhusus = $aksiKhusus;
    }

    /**
     * Pintu modal: event => daftar hak pembuka.
     *
     * @return array<string, array<int, string>>
     */
    public static function pintu(): array
    {
        if (self::$pintu !== null) {
            return self::$pintu;
        }

        $pintu = [];
        foreach (self::kelompok() as $kelompok) {
            foreach ($kelompok['program'] as $program) {
                foreach ($program['pintu'] ?? [] as $event => $pembuka) {
                    if (isset($pintu[$event])) {
                        throw new RuntimeException("Pintu '{$event}' didefinisikan dua kali.");
                    }
                    $pintu[$event] = $pembuka;
                }
            }
        }

        return self::$pintu = $pintu;
    }
}
