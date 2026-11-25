<?php

namespace App\Services;

use App\Models\User;
use App\Support\AksesHalaman;
use Illuminate\Support\Collection;
use App\Support\ProgramAkses;
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
    public const ROLES_PENDAFTARAN = ['mr', 'admin', 'supervisor tu'];

    /**
     * Petugas yang boleh mengentri transaksi Kamar Operasi (dulu KamarOperasiTrait::isAllowedRoleOk).
     * Dokter/Casemix/Mr/Gizi tidak — mereka hanya membuka Dokumen Pelayanan Bedah.
     */
    /**
     * Pengawas transaksi keuangan — centang awal Hapus hutang/DP & Ubah saldo kas.
     * Sama dengan AksiRole::PENGAWAS_KEUANGAN (keputusan user 2026-11-25): Supervisor Tu +
     * manager ke atas, manager = Manager Umum saja.
     */
    public const ROLES_PENGAWAS_KEUANGAN = ['admin', 'manager umum', 'supervisor tu'];

    public const ROLES_TRANSAKSI_OK = ['admin', 'manager umum', 'supervisor penunjang', 'supervisor tu', 'tu', 'perawat'];

    /** Role yang berhak melihat semua menu Master. */
    public const MASTER_ROLES = ['admin', 'manager umum', 'manager medis', 'supervisor penunjang', 'supervisor tu'];

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
     * Definisi semua menu — DIAMBIL dari blok program di app/Akses/Program/*.php
     * (App\Support\ProgramAkses). Helper $entry guard route existence + inject 'href'.
     * Setiap entry: ['group','groupOrder','order','route','title','desc','roles','badge'].
     *
     * Opsional (hak akses, lihat docs/standar-hak-akses.md):
     *  - 'aksi'      => aksi yang BERLAKU di halaman ini (tulis eksplisit);
     *                   kolom lain tampil "·" di Role Control dan tidak dibuat permission-nya.
     *  - 'rolesAksi' => centang awal per aksi bila beda dari 'roles'.
     *  - 'khusus'    => tombol khusus halaman: kunci => [label, roles awal]; permission
     *                   `{route}.{kunci}`, kolom Khusus di baris halaman ini.
     *
     * Konstanta ROLES_* / MASTER_ROLES di atas public karena dipakai berkas program.
     *
     * @param  callable(array):?array  $entry
     * @return array<int, ?array<string, mixed>>
     */
    private static function definitions(callable $entry): array
    {
        return array_map($entry, ProgramAkses::menu());
    }
}
