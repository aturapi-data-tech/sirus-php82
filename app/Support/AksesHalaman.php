<?php

namespace App\Support;

use App\Models\User;
use App\Services\AppMenu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

    /**
     * PINTU MODAL — siapa yang sah membuka sebuah modal lewat event-nya.
     *
     * Masalahnya: tombol pembuka sudah dijaga (`@can` + `#[Authorize]` di method `open*`), tapi
     * komponen modal mendengar event (`#[On('emr-rj.administrasi.open')]`) yang bisa dipicu
     * langsung dari console browser. Satu modal dibuka dari BANYAK halaman dengan hak berbeda,
     * jadi listener-nya dijaga dengan GABUNGAN: lolos bila punya SALAH SATU entri di bawah.
     *
     * Entri berupa:
     *  - nama permission / Gate (`rj.pelayanan.bukaAdministrasi`, `satusehat.kirim`), atau
     *  - `pintu:<event lain>` — rujukan: siapa pun yang lolos pintu itu (modal yang dibuka
     *    dari DALAM modal lain, mis. E-Resep RI dari dalam EMR RI).
     *
     * Listener: `#[Authorize('pintu.<event>')]` (Gate didaftarkan otomatis dari katalog ini).
     * Mode `log` (config akses.mode): pembuka di luar daftar tetap diizinkan tapi dicatat ke
     * storage/logs/akses-halaman-*.log — supaya pembuka sah yang belum terpetakan ketahuan
     * sebelum mode `tolak`. Menambah pembuka baru = tambah entrinya di sini.
     *
     * Peta awal dibangkitkan dari kode (2026-11-25): semua situs pemicu tiap event + penjaganya.
     *
     * @var array<string, array<int, string>>
     */
    public const PINTU = [
        'emr-rj.administrasi.open' => ['rj.pelayanan.bukaAdministrasi', 'rj.antrian-kasir.bukaAdministrasi', 'rj.antrian-apotek.bukaAdministrasi', 'rj.daftar-bulanan.bukaAdministrasi', 'rj.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
        'emr-ugd.administrasi.open' => ['ugd.pelayanan.bukaAdministrasi', 'ugd.antrian-kasir.bukaAdministrasi', 'ugd.antrian-apotek.bukaAdministrasi', 'ugd.daftar-bulanan.bukaAdministrasi', 'ugd.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
        'emr-ri.administrasi.open' => ['ri.daftar.bukaAdministrasi', 'ri.daftar-bulanan.bukaAdministrasi', 'ri.gizi.bukaAdministrasi', 'ri.gizi.bukaAdministrasiEntri', 'kasir.daftar-ri.bukaAdministrasi', 'ri.emr.bukaAdministrasi', 'manajemen.rs.tu.piutang-pasien.lihat'],
        'administrasi-kasir-ri.open' => ['kasir.antrian-ri.bukaAdministrasi'],
        'ri-resep-administrasi.open' => ['ri-resep.antrian.bukaAdministrasi'],
        'cetak-kwitansi-ri-obat.open' => ['pintu:administrasi-kasir-ri.open', 'pintu:ri-resep-administrasi.open'],

        'emr-rj.modul-dokumen.open' => ['rj.daftar.bukaModulDokumen', 'rj.pelayanan.bukaModulDokumen', 'rj.emr.bukaModulDokumen', 'penunjang.kamar-operasi.bukaDokumenBedah'],
        'emr-ugd.modul-dokumen.open' => ['ugd.daftar.bukaModulDokumen', 'ugd.pelayanan.bukaModulDokumen', 'ugd.emr.bukaModulDokumen', 'penunjang.kamar-operasi.bukaDokumenBedah'],
        'emr-ri.modul-dokumen.open' => ['ri.daftar.bukaModulDokumen', 'ri.gizi.bukaModulDokumen', 'ri.gizi.bukaModulDokumenEntri', 'ri.emr.bukaModulDokumen', 'penunjang.kamar-operasi.bukaDokumenBedah'],

        'emr-rj.rekam-medis.open' => ['rj.pelayanan.bukaRekamMedis'],
        'emr-ugd.rekam-medis.open' => ['ugd.pelayanan.bukaRekamMedis'],
        'emr-ri.rekam-medis.open' => ['ri.daftar.bukaRekamMedis'],
        'emr-ri.eresep.open' => ['ri.emr.bukaEresep', 'pintu:emr-ri.rekam-medis.open', 'ri.gizi.lihat'],
        'emr-ri.pindah-kamar.open' => ['ri.pindahKamar'],
        'open-rm-screening-rj' => ['rj.emr.bukaScreening'],
        'rekam-medis-nasional.open' => ['rj.emr.bukaRekamMedisNasional', 'ugd.emr.bukaRekamMedisNasional', 'ri.emr.bukaRekamMedisNasional'],
        'ringkasan-pulang-ri.open' => ['ri.emr.bukaRingkasanPulang'],
        'pelayanan-rj.pengkajian-medis.open' => ['rj.pelayanan.bukaPengkajianMedis'],
        'pelayanan-rj.prmrj.open' => ['rj.pelayanan.bukaPrmrj'],

        'berkas-bpjs.open' => ['ri.daftar.bukaBerkasBpjs', 'ri.daftar-bulanan.bukaBerkasBpjs', 'rj.daftar-bulanan.bukaBerkasBpjs', 'ugd.daftar.bukaBerkasBpjs', 'ugd.daftar-bulanan.bukaBerkasBpjs'],
        'cek-peserta-bpjs.open' => ['ugd.pelayanan.bukaCekPesertaBpjs'],
        'daftar-rj.diagnosa.open' => ['rj.daftar.bukaDiagnosa'],
        'daftar-ugd.diagnosa.open' => ['ugd.daftar.bukaDiagnosa'],
        'daftar-ugd.satu-sehat.open' => ['satusehat.kirim'],
        'daftar-surat-kontrol-bpjs.open' => ['bpjs.hapusSuratKontrol'],
        'riwayat-kontrol.open' => ['rj.daftar.bukaRiwayatKontrol', 'kontrol.jadwal-kontrol.lihat'],

        'open-transfer-rj-ke-ugd' => ['rj.pelayanan.bukaTransferUgd', 'rj.antrian-kasir.bukaTransferUgd'],
        'open-transfer-ugd-ke-ri' => ['ugd.pelayanan.bukaTransferRi', 'ugd.antrian-kasir.bukaTransferRi'],
        'open-ubah-data-kunjungan-rj' => ['rj.ubahDataKunjungan'],
        'open-ubah-data-kunjungan-ugd' => ['ugd.ubahDataKunjungan'],
        'open-ubah-data-kunjungan-ri' => ['ri.ubahDataKunjungan'],
        'open-ubah-data-resep-ri' => ['ri.ubahDataResep'],
    ];

    /** @var array<string, true>|null nama permission yang ada di DB (cache per request) */
    private static ?array $permissionAda = null;

    /** @var array<string, array<int, string>>|null route → roles (lowercase) dari AppMenu */
    private static ?array $rolesMenu = null;

    /** @var array<string, array<int, string>> route → aksi berlaku (hanya yang diisi 'aksi') */
    private static array $aksiMenu = [];

    /** @var array<string, array<string, array<int, string>>> route → aksi → roles awal */
    private static array $rolesAksiMenu = [];

    /** @var array<string, array<string, array{label: string, roles: array<int, string>}>> route → kunci → tombol khusus halaman */
    private static array $khususMenu = [];

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

        return !empty(array_intersect(self::rolesAwal($route, $aksi), self::rolesUser($user)));
    }

    /**
     * Aksi yang berlaku di route (kunci 'aksi' AppMenu; default keempatnya).
     * Route anak (INDUK) ikut induk pertamanya.
     *
     * @return array<int, string>
     */
    public static function aksiBerlaku(string $route): array
    {
        $route = self::INDUK[$route][0] ?? $route;
        self::rolesMenu();

        return self::$aksiMenu[$route] ?? self::AKSI;
    }

    /**
     * Centang awal (cadangan sebelum sinkron + isi `akses:sinkron`) untuk satu aksi:
     * 'rolesAksi'[aksi] bila diisi, selain itu 'roles' menu.
     *
     * @return array<int, string> nama role lowercase
     */
    public static function rolesAwal(string $route, string $aksi): array
    {
        $roles = self::rolesMenu()[$route] ?? [];

        return self::$khususMenu[$route][$aksi]['roles']
            ?? self::$rolesAksiMenu[$route][$aksi]
            ?? $roles;
    }

    /**
     * Tombol khusus halaman (kunci 'khusus' AppMenu), mis. rj.pelayanan → bukaAdministrasi.
     * Permission-nya `{route}.{kunci}`, tampil di kolom Khusus baris halaman itu.
     *
     * @return array<string, array{label: string, roles: array<int, string>}>
     */
    public static function khususHalaman(string $route): array
    {
        self::rolesMenu();

        return self::$khususMenu[$route] ?? [];
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
     * Untuk Gate::before: ability berbentuk `{route}.{aksi|kunciKhusus}` dengan route dikenal → bool;
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
        if (!self::dikenal($route)) {
            return null;
        }
        if (!in_array($aksi, self::AKSI, true) && !isset(self::khususHalaman(self::INDUK[$route][0] ?? $route)[$aksi])) {
            return null;
        }

        return self::boleh($user, $route, $aksi);
    }

    /** Lolos pintu modal `$event` (katalog PINTU)? Rujukan `pintu:` ditelusuri, aman dari putaran. */
    public static function lolosPintu(?User $user, string $event, array $sudah = []): bool
    {
        if (!$user || !isset(self::PINTU[$event]) || in_array($event, $sudah, true)) {
            return false;
        }
        $sudah[] = $event;
        foreach (self::PINTU[$event] as $entri) {
            $lolos = str_starts_with($entri, 'pintu:')
                ? self::lolosPintu($user, substr($entri, 6), $sudah)
                : $user->can($entri);
            if ($lolos) {
                return true;
            }
        }

        return false;
    }

    /**
     * Untuk Gate `pintu.<event>`: di mode `log` pembuka tak terdaftar tetap boleh tapi dicatat
     * (pembuka sah yang belum terpetakan ketahuan dari log); di mode `tolak` ditolak.
     */
    public static function gatePintu(?User $user, string $event): bool
    {
        if (self::lolosPintu($user, $event)) {
            return true;
        }

        $kunci = 'akses-pintu:' . ($user?->id ?? '-') . ':' . $event . ':' . now()->format('Ymd');
        if (Cache::add($kunci, 1, now()->endOfDay())) Log::channel('akses')->warning('Pintu modal di luar hak', [
            'mode' => config('akses.mode'),
            'user_id' => $user?->id,
            'roles' => $user?->getRoleNames()->implode(', '),
            'pintu' => $event,
            'url' => request()->header('referer') ?: request()->fullUrl(),
        ]);

        return config('akses.mode') !== 'tolak';
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
        self::$aksiMenu = [];
        self::$rolesAksiMenu = [];
        self::$khususMenu = [];
        $kecil = fn (array $roles) => array_map(fn ($r) => trim(strtolower($r)), $roles);
        foreach (AppMenu::all() as $m) {
            $route = $m['route'];
            $peta[$route] = array_values(array_unique([...($peta[$route] ?? []), ...$kecil($m['roles'])]));
            if (isset($m['aksi'])) {
                self::$aksiMenu[$route] = array_values(array_intersect(self::AKSI, $m['aksi']));
            }
            foreach ($m['rolesAksi'] ?? [] as $aksi => $roles) {
                self::$rolesAksiMenu[$route][$aksi] = $kecil($roles);
            }
            foreach ($m['khusus'] ?? [] as $kunci => [$label, $roles]) {
                self::$khususMenu[$route][$kunci] = ['label' => $label, 'roles' => $kecil($roles)];
            }
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
