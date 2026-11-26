<?php

namespace App\Console\Commands;

use App\Services\AppMenu;
use App\Support\AksesHalaman;
use App\Support\AksiRole;
use App\Support\SinkronAksesOtomatis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Membuat permission `{route}.{lihat|tambah|ubah|hapus}` untuk setiap menu di AppMenu,
 * ditambah aksi khusus (AksiRole::khusus(), dari berkas program app/Akses/Program/<Kelompok>).
 *
 * Centang awal disalin dari kolom 'roles' AppMenu HANYA untuk permission yang baru
 * dibuat pada run ini. Permission yang sudah ada tidak disentuh — jadi menjalankan
 * ulang aman: centang yang sudah dicabut admin di Role Control tidak kembali.
 * Menu baru yang ditambah belakangan cukup dijalankan ulang perintah ini.
 *
 * Centang awal per aksi = 'rolesAksi' entri AppMenu bila diisi, selain itu 'roles'.
 * Hanya aksi yang berlaku ('aksi' entri AppMenu) yang dibuat permission-nya.
 *
 * Biasanya TIDAK perlu dijalankan manual: App\Support\SinkronAksesOtomatis menjalankannya
 * sendiri pada request pertama sesudah AppMenu.php / AksiRole.php berubah (habis deploy).
 * Manual tetap dipakai untuk --dry-run dan --ulang.
 *
 * `--ulang=rj.daftar,ugd.daftar` : isi ulang centang route itu dari definisi (menimpa
 * Role Control untuk route itu saja) — dipakai saat definisi awal sebuah route dikoreksi.
 *
 * BERSIH-BERSIH: permission yang tidak lagi lahir dari definisi (menu/aksi dihapus, aksi
 * tak berlaku, nama lama yang sudah dipecah per jalur, buatan manual) DIHAPUS beserta
 * centangnya — tak ada kode yang memeriksanya, jadi hak siapa pun tidak berubah.
 * Nama lama sumber 'menggantikan' baru dihapus sesudah semua penggantinya ada (centang
 * sudah diwariskan). Daftar yang dihapus + role-nya dicatat ke log channel 'akses'.
 */
class SinkronAksesHalaman extends Command
{
    protected $signature = 'akses:sinkron
        {--dry-run : Tampilkan rencana tanpa menulis ke DB}
        {--ulang= : Route AppMenu / nama aksi khusus (pisah koma) yang centangnya DIISI ULANG dari definisi — menimpa centang Role Control untuk itu saja}';

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
            if (!isset(AksesHalaman::rolesMenu()[$r]) && !isset(AksiRole::khusus()[$r])) {
                $this->error("--ulang: '{$r}' bukan route AppMenu maupun aksi khusus (berkas program, kunci 'aksiKhusus')");
                return self::FAILURE;
            }
        }
        $diulang = 0;
        $diwarisi = 0;
        $dikenal = []; // nama permission yang lahir dari definisi

        foreach (AksesHalaman::rolesMenu() as $route => $roles) {
            if (in_array(AppMenu::SEMUA_ROLE, $roles, true)) {
                continue; // terbuka untuk semua, tak perlu permission
            }

            // 4 aksi halaman yang berlaku + tombol khusus halaman ('khusus' AppMenu)
            foreach ([...AksesHalaman::aksiBerlaku($route), ...array_keys(AksesHalaman::khususHalaman($route))] as $aksi) {
                $targetRole = [];
                foreach (AksesHalaman::rolesAwal($route, $aksi) as $r) {
                    if (isset($roleDb[$r])) {
                        $targetRole[] = $roleDb[$r];
                    } else {
                        $roleTakDikenal[$r] = true;
                    }
                }

                $nama = AksesHalaman::namaPermission($route, $aksi);
                $dikenal[$nama] = true;
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
        foreach (AksiRole::khusus() as $nama => $aksi) {
            $dikenal[$nama] = true;
            $targetRole = [];
            foreach ($aksi['roles'] as $r) {
                $kunci = trim(strtolower($r));
                if (isset($roleDb[$kunci])) {
                    $targetRole[] = $roleDb[$kunci];
                } else {
                    $roleTakDikenal[$kunci] = true;
                }
            }
            if (isset($ada[$nama])) {
                if (in_array($nama, $ulang, true)) {
                    $diulang++;
                    $this->line("  ulang {$nama}: " . collect($targetRole)->pluck('name')->implode(', '));
                    if (!$dryRun) {
                        Permission::findByName($nama, 'web')->syncRoles($targetRole);
                    }
                }
                continue;
            }
            // 'menggantikan' => nama hak lama yang dipecah/diganti (mis. dokumen.hapus → rj.dokumen.hapus):
            // centangnya DIWARISKAN dari hak lama (termasuk yang sudah diatur admin di Role Control),
            // bukan dari roles awal — pemecahan hak tidak boleh menambah/mengurangi hak siapa pun.
            $sumberWaris = $aksi['menggantikan'] ?? null;
            if ($sumberWaris !== null && isset($ada[$sumberWaris])) {
                $targetRole = Permission::findByName($sumberWaris, 'web')->roles()->get()->all();
                $diwarisi++;
                $this->line("  waris {$nama} ← {$sumberWaris}: " . collect($targetRole)->pluck('name')->implode(', '));
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

        $dibersihkan = $this->bersihkan($dikenal, $dryRun);

        if (!$dryRun) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            AksesHalaman::lupakanCache();
            SinkronAksesOtomatis::tulisTanda(); // sinkron otomatis tak perlu mengulang
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Permission baru: {$baru}, centang role: {$centang}" . ($ulang ? ", diisi ulang: {$diulang}" : '') . ($diwarisi ? ", mewarisi centang hak lama: {$diwarisi}" : '') . ($dibersihkan ? ", sisa lama dihapus: {$dibersihkan}" : ''));
        if ($roleTakDikenal) {
            $this->warn('Role di AppMenu tapi tidak ada di tabel roles: ' . implode(', ', array_keys($roleTakDikenal)));
        }

        return self::SUCCESS;
    }

    /**
     * Hapus permission di luar definisi. Nama lama sumber 'menggantikan' ditahan selama masih
     * ada penggantinya yang belum dibuat (hanya terjadi di --dry-run: di run biasa pengganti
     * sudah dibuat di atas, centangnya sudah diwariskan).
     *
     * @param array<string, true> $dikenal
     */
    private function bersihkan(array $dikenal, bool $dryRun): int
    {
        $sekarang = Permission::where('guard_name', 'web')->pluck('name')->flip();
        $ditahan = [];
        foreach (AksiRole::khusus() as $nama => $aksi) {
            if (isset($aksi['menggantikan']) && !isset($sekarang[$nama])) {
                $ditahan[$aksi['menggantikan']] = true;
            }
        }

        $sisa = Permission::where('guard_name', 'web')->with('roles')->orderBy('name')->get()
            ->reject(fn ($p) => isset($dikenal[$p->name]) || isset($ditahan[$p->name]));
        if ($sisa->isEmpty()) {
            return 0;
        }

        $catatan = $sisa->mapWithKeys(fn ($p) => [$p->name => $p->roles->pluck('name')->implode(', ')])->all();
        foreach ($catatan as $nama => $roles) {
            $this->line("  hapus {$nama}" . ($roles !== '' ? " (centang: {$roles})" : ''));
        }
        if ($dryRun) {
            return $sisa->count();
        }

        DB::transaction(function () use ($sisa) {
            foreach ($sisa as $permission) {
                $permission->delete(); // Spatie ikut melepas kaitan role & user
            }
        });
        Log::channel('akses')->warning('akses:sinkron menghapus permission di luar definisi', ['permission' => $catatan]);

        return $sisa->count();
    }
}
