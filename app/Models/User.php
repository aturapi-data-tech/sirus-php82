<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Support\AksiRole;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Profesi klinis user untuk pencatatan TTD CPPT/SBAR.
     *
     * User multi-role (mis. Perawat + Dokter, Perawat + Manager) tercatat
     * dengan profesi yang salah kalau diambil dari roles->first() — urutan
     * role di pivot arbitrer. Kolom users.myuser_profesi (di-set di menu
     * User Control) jadi penentu tetap; kalau kosong fallback ke role pertama.
     *
     * Kekecualian role penunjang: CPPT & SBAR mengelompokkan catatan per profesi
     * dan tab-nya bernama 'Penunjang', bukan 'Laboratorium'/'Radiologi'. Tanpa
     * pemetaan ini entri mereka tersimpan dengan profesi yang tak punya tab —
     * hanya kelihatan di tab "Semua", tidak terhitung di badge, dan tombol Copy
     * (syarat profesi sama) jadi kacau. myuser_profesi tetap menang bila diisi.
     */
    public function profesiKlinis(): string
    {
        if ($this->myuser_profesi) {
            return $this->myuser_profesi;
        }

        $role = $this->roles->first()->name ?? '';

        return in_array($role, AksiRole::EMR_PENUNJANG_LIHAT, true) ? 'Penunjang' : $role;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
