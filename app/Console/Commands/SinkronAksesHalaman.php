<?php

namespace App\Console\Commands;

use App\Services\AppMenu;
use App\Support\AksesHalaman;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat permission `{route}.{lihat|tambah|ubah|hapus}` untuk setiap menu di AppMenu.
 *
 * Centang awal disalin dari kolom 'roles' AppMenu HANYA untuk permission yang baru
 * dibuat pada run ini. Permission yang sudah ada tidak disentuh — jadi menjalankan
 * ulang aman: centang yang sudah dicabut admin di Role Control tidak kembali.
 * Menu baru yang ditambah belakangan cukup dijalankan ulang perintah ini.
 */
class SinkronAksesHalaman extends Command
{
    protected $signature = 'akses:sinkron {--dry-run : Tampilkan rencana tanpa menulis ke DB}';

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

        foreach (AksesHalaman::rolesMenu() as $route => $roles) {
            if (in_array(AppMenu::SEMUA_ROLE, $roles, true)) {
                continue; // terbuka untuk semua, tak perlu permission
            }

            $targetRole = [];
            foreach ($roles as $r) {
                if (isset($roleDb[$r])) {
                    $targetRole[] = $roleDb[$r];
                } else {
                    $roleTakDikenal[$r] = true;
                }
            }

            foreach (AksesHalaman::AKSI as $aksi) {
                $nama = AksesHalaman::namaPermission($route, $aksi);
                if (isset($ada[$nama])) {
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

        if (!$dryRun) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            AksesHalaman::lupakanCache();
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Permission baru: {$baru}, centang role: {$centang}");
        if ($roleTakDikenal) {
            $this->warn('Role di AppMenu tapi tidak ada di tabel roles: ' . implode(', ', array_keys($roleTakDikenal)));
        }

        return self::SUCCESS;
    }
}
