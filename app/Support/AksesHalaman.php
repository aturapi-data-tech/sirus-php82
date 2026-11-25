<?php

namespace App\Support;

use App\Models\User;
use App\Services\AppMenu;
use Spatie\Permission\PermissionRegistrar;

/**
 * AksesHalaman — aturan siapa boleh membuka halaman (route) dan melakukan aksi di dalamnya.
 *
 * Konsep: User → Role → Permission `{nama-route}.{aksi}` (mis. `master.poli.lihat`).
 * Centang permission diatur per ROLE di Role Control; user mendapat hak dari role-nya
 * (gabungan bila user punya beberapa role).
 *
 * Urutan keputusan `boleh()`:
 *  1. Route terbuka (dashboard, profil, berkas) atau menu ber-roles ['*'] → boleh.
 *  2. Route anak (laporan yang dibuka dari halaman hub) → ikut hak induknya.
 *  3. Permission `{route}.{aksi}` sudah ada di DB → pakai centang di Role Control.
 *  4. Permission belum dibuat (perintah `akses:sinkron` belum dijalankan) → cadangan:
 *     daftar `roles` di AppMenu, supaya deploy tanpa sinkron tidak mengunci siapa pun.
 *  5. Route tidak dikenal sama sekali → ditolak (deny-by-default).
 */
class AksesHalaman
{
    public const AKSI = ['lihat', 'tambah', 'ubah', 'hapus'];

    /** Route yang boleh dibuka semua user login tanpa centang apa pun. */
    public const ROUTE_TERBUKA = [
        'dashboard',
        'home',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'files.show',
    ];

    /**
     * Halaman pengatur hak akses: SELALU ditolak (403) bagi yang tak berhak walau mode
     * masih 'log' — di sini user bisa menaikkan haknya sendiri.
     */
    public const ROUTE_SELALU_DIJAGA = [
        'database-monitor.role-control',
        'database-monitor.user-control',
    ];

    /** Role yang tidak boleh terkunci dari Role Control (cegah admin mencabut centangnya sendiri). */
    private const ROLE_PENGATUR_AKSES = 'admin';

    /**
     * Route yang tidak tampil di menu karena dibuka dari halaman lain (hub).
     * Hak aksesnya = hak salah satu induk (cukup boleh di salah satunya).
     *
     * @var array<string, array<int, string>>
     */
    public const INDUK = [
        'manajemen.laporan-diagnosa.hitung-diagnosa' => ['manajemen.laporan-diagnosa'],
        'manajemen.sirs.ri.laporan-rl-4-1-morbiditas' => ['manajemen.laporan-diagnosa'],
        'manajemen.sirs.ri.laporan-rl-4-2-10besar' => ['manajemen.laporan-diagnosa'],
        'manajemen.sirs.ri.laporan-rl-4-3-10besar-mati' => ['manajemen.laporan-diagnosa'],
        'manajemen.sirs.rj.laporan-rl-5-1-morbiditas' => ['manajemen.laporan-diagnosa'],
        'manajemen.sirs.rj.laporan-rl-5-3-10besar-kunjungan' => ['manajemen.laporan-diagnosa'],

        'manajemen.rs.penunjang.lab.laporan-nilai-kritis' => ['manajemen.indikator-penunjang'],
        'manajemen.rs.penunjang.lab.laporan-pemeriksaan-dalam-luar' => ['manajemen.indikator-penunjang'],
        'manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad-detail' => ['manajemen.indikator-penunjang'],
        'manajemen.rs.penunjang.lab.laporan-pemeriksaan-lab' => ['manajemen.indikator-penunjang', 'manajemen.indikator-pelayanan'],
        'manajemen.rs.penunjang.lab.laporan-permintaan-lab' => ['manajemen.indikator-penunjang', 'manajemen.indikator-pelayanan'],
        'manajemen.rs.penunjang.rad.laporan-pemeriksaan-rad' => ['manajemen.indikator-penunjang', 'manajemen.indikator-pelayanan'],
        'manajemen.rs.penunjang.rad.laporan-permintaan-rad' => ['manajemen.indikator-penunjang', 'manajemen.indikator-pelayanan'],
        'manajemen.sirs.penunjang.laporan-rl-3-8-laboratorium' => ['manajemen.indikator-penunjang', 'manajemen.indikator-pelayanan'],
        'manajemen.sirs.penunjang.laporan-rl-3-9-radiologi' => ['manajemen.indikator-penunjang', 'manajemen.indikator-pelayanan'],

        'manajemen.rs.penunjang.ok.laporan-kasus-operasi' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.ri.laporan-hak-kelas-ri' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.ri.laporan-kunjungan-ri' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.ri.laporan-surveilans-hais' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.rj.laporan-hak-kelas-rj' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.rj.laporan-kunjungan-rj' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.rj.laporan-task-id-rj' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.satu-sehat.monitoring-satu-sehat' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.ugd.laporan-hak-kelas-ugd' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.ugd.laporan-kunjungan-ugd' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.ugd.laporan-task-id-ugd' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.vclaim.laporan-rujukan-keluar' => ['manajemen.indikator-pelayanan'],
        'manajemen.rs.vclaim.laporan-rujukan-masuk' => ['manajemen.indikator-pelayanan'],
        'manajemen.sirs.ri.laporan-rl-3-2-rawat-inap' => ['manajemen.indikator-pelayanan'],
        'manajemen.sirs.ri.laporan-rl-3-19-cara-bayar' => ['manajemen.indikator-pelayanan', 'manajemen.indikator-tu'],
        'manajemen.sirs.rj.laporan-rl-3-15-kesehatan-jiwa' => ['manajemen.indikator-pelayanan'],
        'manajemen.sirs.rj.laporan-rl-3-4-pengunjung' => ['manajemen.indikator-pelayanan'],
        'manajemen.sirs.rj.laporan-rl-3-5-kunjungan' => ['manajemen.indikator-pelayanan'],
        'manajemen.sirs.ugd.laporan-rl-3-3-rawat-darurat' => ['manajemen.indikator-pelayanan'],
        'manajemen.transfer-antar-ruangan' => ['manajemen.indikator-pelayanan'],

        'manajemen.rs.tu.laporan-operasi' => ['manajemen.monitoring-keuangan'],
        'manajemen.rs.tu.monitoring-jasa-dokter' => ['manajemen.monitoring-keuangan'],
        'manajemen.rs.tu.pendapatan-jasa-dokter' => ['manajemen.monitoring-keuangan'],
        'manajemen.rs.tu.pendapatan-jasa-karyawan' => ['manajemen.monitoring-keuangan'],
        'manajemen.rs.tu.pendapatan-jasa-medis' => ['manajemen.monitoring-keuangan'],
        'manajemen.rs.tu.pendapatan-rs' => ['manajemen.monitoring-keuangan'],
        'manajemen.rs.tu.piutang-pasien' => ['manajemen.monitoring-keuangan'],

        'keuangan.tutup-buku-supplier' => ['keuangan.tutup-buku'],
        'panduan-dev.koding-formulir-rm' => ['panduan-dev'],
    ];

    /** @var array<string, true>|null nama permission yang ada di DB (cache per request) */
    private static ?array $permissionAda = null;

    /** @var array<string, array<int, string>>|null route → roles (lowercase) dari AppMenu */
    private static ?array $rolesMenu = null;

    public static function namaPermission(string $route, string $aksi): string
    {
        return $route . '.' . $aksi;
    }

    public static function boleh(?User $user, ?string $route, string $aksi = 'lihat'): bool
    {
        if (!$user || !$route) {
            return false;
        }

        if (in_array($route, self::ROUTE_TERBUKA, true)) {
            return true;
        }

        if ($route === 'database-monitor.role-control' && self::punyaRole($user, self::ROLE_PENGATUR_AKSES)) {
            return true;
        }

        if (isset(self::INDUK[$route])) {
            foreach (self::INDUK[$route] as $induk) {
                if (self::boleh($user, $induk, $aksi)) {
                    return true;
                }
            }
            return false;
        }

        $rolesMenu = self::rolesMenu()[$route] ?? null;
        if ($rolesMenu === null) {
            return false;
        }
        if (in_array(AppMenu::SEMUA_ROLE, $rolesMenu, true)) {
            return true;
        }

        $permission = self::namaPermission($route, $aksi);
        if (self::permissionAda($permission)) {
            return $user->checkPermissionTo($permission);
        }

        return !empty(array_intersect($rolesMenu, self::rolesUser($user)));
    }

    /** @return array<int, string> nama role user, lowercase + trim (nama role di DB pernah ber-spasi) */
    private static function rolesUser(User $user): array
    {
        return $user->getRoleNames()->map(fn ($r) => trim(strtolower($r)))->all();
    }

    private static function punyaRole(User $user, string $role): bool
    {
        return in_array($role, self::rolesUser($user), true);
    }

    /**
     * Aksi khusus (katalog AksiRole::KHUSUS, mis. `dokumen.hapus`, `ri.cppt.ubahSemua`).
     * Permission sudah dibuat → centang Role Control; belum → cadangan roles katalog.
     * Dipanggil lewat Gate (AppServiceProvider), jadi pemakai cukup `@can('dokumen.hapus')`.
     */
    public static function bolehKhusus(?User $user, string $nama): bool
    {
        if (!$user || !isset(AksiRole::KHUSUS[$nama])) {
            return false;
        }

        if (self::permissionAda($nama)) {
            return $user->checkPermissionTo($nama);
        }

        return $user->hasAnyRole(AksiRole::KHUSUS[$nama]['roles']);
    }

    /**
     * Untuk Gate::before: ability berbentuk `{route}.{aksi}` dengan route dikenal → bool;
     * selain itu null supaya Gate lain (aksi khusus, Gate lama) yang memutuskan.
     */
    public static function aturAbility(?User $user, string $ability): ?bool
    {
        $titik = strrpos($ability, '.');
        if ($titik === false) {
            return null;
        }
        $aksi = substr($ability, $titik + 1);
        $route = substr($ability, 0, $titik);
        if (!in_array($aksi, self::AKSI, true) || !self::dikenal($route)) {
            return null;
        }

        return self::boleh($user, $route, $aksi);
    }

    /** Route dikenal oleh aturan akses (ada di menu, induk, atau terbuka)? */
    public static function dikenal(string $route): bool
    {
        return in_array($route, self::ROUTE_TERBUKA, true)
            || isset(self::INDUK[$route])
            || isset(self::rolesMenu()[$route]);
    }

    /**
     * Gabungan roles per route dari AppMenu (satu route bisa muncul di beberapa entri menu,
     * mis. Casemix ber-tab). Dipakai sebagai cadangan & isi awal `akses:sinkron`.
     *
     * @return array<string, array<int, string>>
     */
    public static function rolesMenu(): array
    {
        if (self::$rolesMenu !== null) {
            return self::$rolesMenu;
        }

        $peta = [];
        foreach (AppMenu::all() as $m) {
            $roles = array_map(fn ($r) => trim(strtolower($r)), $m['roles']);
            $peta[$m['route']] = array_values(array_unique([...($peta[$m['route']] ?? []), ...$roles]));
        }

        return self::$rolesMenu = $peta;
    }

    private static function permissionAda(string $nama): bool
    {
        if (self::$permissionAda === null) {
            self::$permissionAda = app(PermissionRegistrar::class)
                ->getPermissions(['guard_name' => 'web'])
                ->pluck('name')
                ->flip()
                ->map(fn () => true)
                ->all();
        }

        return isset(self::$permissionAda[$nama]);
    }

    /** Dipanggil setelah permission dibuat/diubah dalam request yang sama (perintah sinkron, Role Control). */
    public static function lupakanCache(): void
    {
        self::$permissionAda = null;
        self::$rolesMenu = null;
    }
}
