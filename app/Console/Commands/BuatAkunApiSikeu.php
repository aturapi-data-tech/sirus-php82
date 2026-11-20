<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Buat / setel ulang akun API SIKEU (keuangan): user di tabel users dengan role services.sikeu.role ('Api Sikeu').
 * Akun ini hanya untuk mesin — ditolak di login web (LoginRequest), tidak punya menu. Password dicetak sekali;
 * salin ke .env SIKEU (SIKEU_API_USER / SIKEU_API_PASSWORD).
 *
 *   php artisan sikeu-api:akun                 → user sikeu-jurnal, password acak
 *   php artisan sikeu-api:akun sikeu-jurnal --password=...   → password ditentukan
 */
class BuatAkunApiSikeu extends Command
{
    protected $signature = 'sikeu-api:akun {nama=sikeu-jurnal : Username akun API} {--password= : Password (kosong = acak 32 karakter)}';

    protected $description = 'Buat atau setel ulang akun API SIKEU (user ber-role Api Sikeu untuk integrasi keuangan)';

    public function handle(): int
    {
        $nama = trim((string) $this->argument('nama'));
        if ($nama === '' || !preg_match('/^[a-z0-9._-]{3,40}$/i', $nama)) {
            $this->error('Nama akun: 3–40 karakter huruf/angka/titik/strip.');
            return self::FAILURE;
        }
        $roleNama = (string) config('services.sikeu.role', 'Api Sikeu');
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
            $user = User::create(['name' => $nama, 'email' => "{$nama}@api.sikeu.local", 'password' => Hash::make($password)]);
            $user->forceFill(['myuser_name' => 'Akun API SIKEU (keuangan)', 'active_status' => '1'])->save();
            $this->info("Akun {$nama} dibuat.");
        }
        $user->syncRoles([$role->name]);

        $this->line('');
        $this->line("Isi di .env SIKEU:");
        $this->line("  SIKEU_API_USER={$nama}");
        $this->line("  SIKEU_API_PASSWORD={$password}");
        $this->line('');
        $this->comment("Role: {$role->name}. Nonaktifkan / ganti password kapan saja dari User Control atau jalankan perintah ini lagi.");

        return self::SUCCESS;
    }
}
