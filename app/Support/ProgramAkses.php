<?php

namespace App\Support;

use RuntimeException;

/**
 * SUMBER TUNGGAL definisi hak akses — SATU BLOK PER PROGRAM.
 *
 * Satu program (mis. "Pelayanan Rawat Jalan", "Administrasi & Kasir Rawat Jalan") ditulis
 * lengkap di satu tempat, berkas per kelompok di app/Akses/Program/*.php:
 *
 *   'Pelayanan Rawat Jalan' => [
 *       'menu'       => [ [entri menu: route, aksi, khusus, roles, ...] ],   // → AppMenu
 *       'aksiKhusus' => [ 'rj.detailKunjungan' => ['label' => ..., 'roles' => ...] ],  // → Gate
 *       'pintu'      => [ 'emr-rj.rekam-medis.open' => [hak pembuka...] ],   // → listener modal
 *   ],
 *
 * AppMenu::all(), AksiRole::khusus() dan AksesHalaman::pintu() membaca dari sini, dan Role
 * Control menampilkan matriks per kelompok → program dengan urutan yang sama.
 *
 * Menambah program/aksi: tulis di blok programnya, pasang @can + #[Authorize] di komponen,
 * deploy — permission + centang awal dibuat otomatis (SinkronAksesOtomatis).
 */
class ProgramAkses
{
    /** Urutan kelompok = urutan tampil di Role Control. Nama berkas di app/Akses/Program. */
    private const BERKAS = [
        'LaporanManajemen',
        'Master',
        'RawatJalan',
        'Ugd',
        'RawatInap',
        'Casemix',
        'LintasJalur',
        'Penunjang',
        'Gudang',
        'Keuangan',
        'Sistem',
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
        foreach (self::BERKAS as $berkas) {
            $isi = require app_path("Akses/Program/{$berkas}.php");
            if (!is_array($isi) || !isset($isi['judul'], $isi['program'])) {
                throw new RuntimeException("Definisi hak akses {$berkas}.php tidak berisi 'judul' & 'program'.");
            }
            $kelompok[] = $isi;
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
