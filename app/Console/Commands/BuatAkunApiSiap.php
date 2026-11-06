<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Buat / setel ulang akun API SIAP (penggajian): user di tabel users dengan role services.siap.role ('Api Siap').
 * Akun ini hanya untuk mesin — ditolak di login web (LoginRequest), tidak punya menu. Password dicetak sekali;
 * salin ke .env SIAP (SIRUS_API_USER / SIRUS_API_PASSWORD).
 *
 *   php artisan siap-api:akun                 → user siap-gaji, password acak
 *   php artisan siap-api:akun siap-gaji --password=...   → password ditentukan
 */
class BuatAkunApiSiap extends Command
{
    protected $signature = 'siap-api:akun {nama=siap-gaji : Username akun API} {--password= : Password (kosong = acak 32 karakter)}';

    protected $description = 'Buat atau setel ulang akun API SIAP (user ber-role Api Siap untuk integrasi penggajian)';

    public function handle(): int
    {
        $nama = trim((string) $this->argument('nama'));
        if ($nama === '' || !preg_match('/^[a-z0-9._-]{3,40}$/i', $nama)) {
            $this->error('Nama akun: 3–40 karakter huruf/angka/titik/strip.');
            return self::FAILURE;
        }
        $roleNama = (string) config('services.siap.role', 'Api Siap');
        $role = Role::firstOrCreate(['name' => $roleNama, 'guard_name' => 'web']);
        $password = (string) ($this->option('password') ?: Str::random(32));

        $user = User::where('name', $nama)->first();
        if ($user) {
            if ($user->roles->isNotEmpty() && !$user->hasRole($roleNama)) {
                $this->error("User {$nama} sudah ada dengan role lain ({$user->roles->pluck('name')->implode(', ')}) — pakai nama lain, jangan ubah akun orang.");
                return self::FAILURE;
            }
            $user->forceFill(['password' => Hash::make($password), 'active_status' => '1'])->save();
            $this->info("Password akun {$nama} disetel ulang.");
        } else {
            $user = User::create(['name' => $nama, 'email' => "{$nama}@api.siap.local", 'password' => Hash::make($password)]);
            $user->forceFill(['myuser_name' => 'Akun API SIAP (penggajian)', 'active_status' => '1'])->save();
            $this->info("Akun {$nama} dibuat.");
        }
        $user->syncRoles([$role->name]);

        $this->line('');
        $this->line("Isi di .env SIAP:");
        $this->line("  SIRUS_API_USER={$nama}");
        $this->line("  SIRUS_API_PASSWORD={$password}");
        $this->line('');
        $this->comment("Role: {$role->name}. Nonaktifkan / ganti password kapan saja dari User Control atau jalankan perintah ini lagi.");

        return self::SUCCESS;
    }
}
