<?php

namespace App\Console\Commands;

use App\Services\AppMenu;
use App\Support\AksesHalaman;
use App\Support\AksiRole;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat permission `{route}.{lihat|tambah|ubah|hapus}` untuk setiap menu di AppMenu,
 * ditambah aksi khusus dari AksiRole::KHUSUS (kolom "Khusus" Role Control).
 *
 * Centang awal disalin dari kolom 'roles' AppMenu HANYA untuk permission yang baru
 * dibuat pada run ini. Permission yang sudah ada tidak disentuh — jadi menjalankan
 * ulang aman: centang yang sudah dicabut admin di Role Control tidak kembali.
 * Menu baru yang ditambah belakangan cukup dijalankan ulang perintah ini.
 *
 * Centang awal per aksi = 'rolesAksi' entri AppMenu bila diisi, selain itu 'roles'.
 * Hanya aksi yang berlaku ('aksi' entri AppMenu) yang dibuat permission-nya.
 *
 * `--ulang=rj.daftar,ugd.daftar` : isi ulang centang route itu dari definisi (menimpa
 * Role Control untuk route itu saja) — dipakai saat definisi awal sebuah route dikoreksi.
 */
class SinkronAksesHalaman extends Command
{
    protected $signature = 'akses:sinkron
        {--dry-run : Tampilkan rencana tanpa menulis ke DB}
        {--ulang= : Route (pisah koma) yang centangnya DIISI ULANG dari AppMenu — menimpa centang Role Control route itu}';

    protected $description = 'Buat permission halaman dari AppMenu dan salin centang awal dari roles menu';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $roleDb = Role::where('guard_name', 'web')->get()
            ->keyBy(fn ($r) => trim(strtolower($r->name)));

        $ada = Permission::where('guard_name', 'web')->pluck('name')->flip();

        $baru = 0;
        $centang = 0;
        $roleTakDikenal = [];

        $ulang = array_filter(array_map('trim', explode(',', (string) $this->option('ulang'))));
        foreach ($ulang as $r) {
            if (!isset(AksesHalaman::rolesMenu()[$r])) {
                $this->error("--ulang: route '{$r}' tidak ada di AppMenu");
                return self::FAILURE;
            }
        }
        $diulang = 0;

        foreach (AksesHalaman::rolesMenu() as $route => $roles) {
            if (in_array(AppMenu::SEMUA_ROLE, $roles, true)) {
                continue; // terbuka untuk semua, tak perlu permission
            }

            foreach (AksesHalaman::aksiBerlaku($route) as $aksi) {
                $targetRole = [];
                foreach (AksesHalaman::rolesAwal($route, $aksi) as $r) {
                    if (isset($roleDb[$r])) {
                        $targetRole[] = $roleDb[$r];
                    } else {
                        $roleTakDikenal[$r] = true;
                    }
                }

                $nama = AksesHalaman::namaPermission($route, $aksi);
                if (isset($ada[$nama])) {
                    // Sudah ada: centang admin dihormati, KECUALI route diminta --ulang.
                    if (in_array($route, $ulang, true)) {
                        $diulang++;
                        $this->line("  ulang {$nama}: " . collect($targetRole)->pluck('name')->implode(', '));
                        if (!$dryRun) {
                            Permission::findByName($nama, 'web')->syncRoles($targetRole);
                        }
                    }
                    continue;
                }
                $baru++;
                $centang += count($targetRole);

                if ($dryRun) {
                    continue;
                }

                $permission = Permission::create(['name' => $nama, 'guard_name' => 'web']);
                foreach ($targetRole as $role) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        // Aksi khusus (kolom "Khusus" Role Control) — sama: hanya yang baru disalin centangnya.
        foreach (AksiRole::KHUSUS as $nama => $aksi) {
            if (isset($ada[$nama])) {
                continue;
            }
            $targetRole = [];
            foreach ($aksi['roles'] as $r) {
                $kunci = trim(strtolower($r));
                if (isset($roleDb[$kunci])) {
                    $targetRole[] = $roleDb[$kunci];
                } else {
                    $roleTakDikenal[$kunci] = true;
                }
            }
            $baru++;
            $centang += count($targetRole);
            if ($dryRun) {
                continue;
            }
            $permission = Permission::create(['name' => $nama, 'guard_name' => 'web']);
            foreach ($targetRole as $role) {
                $role->givePermissionTo($permission);
            }
        }

        if (!$dryRun) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            AksesHalaman::lupakanCache();
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Permission baru: {$baru}, centang role: {$centang}" . ($ulang ? ", diisi ulang: {$diulang}" : ''));
        if ($roleTakDikenal) {
            $this->warn('Role di AppMenu tapi tidak ada di tabel roles: ' . implode(', ', array_keys($roleTakDikenal)));
        }

        return self::SUCCESS;
    }
}
