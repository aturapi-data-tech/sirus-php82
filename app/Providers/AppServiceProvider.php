<?php

namespace App\Providers;

use App\Services\AppMenu;
use App\Support\AksesHalaman;
use App\Support\AksiRole;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production', 'staging')
            || request()->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || request()->isSecure()) {
            URL::forceScheme('https');
        }

        // Penjaga URL halaman ikut berjalan di setiap aksi Livewire (/livewire/update),
        // bukan cuma saat halaman dibuka. Hanya berlaku untuk route yang memakai
        // middleware akses.halaman (routes/web.php).
        Livewire::addPersistentMiddleware([\App\Http\Middleware\CekAksesHalaman::class]);

        // Rate limiter API Mobile JKN (route di bootstrap/app.php, middleware throttle:mobile-jkn).
        // WAJIB di sini, bukan di callback `then:` withRouting: callback itu tidak dijalankan saat
        // route di-cache (php artisan optimize / route:cache) → "Rate limiter [mobile-jkn] is not
        // defined" dan SEMUA request Mobile JKN gagal 500 (kejadian 2026-10-24, 582× sehari).
        RateLimiter::for('mobile-jkn', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        // API SIAP (penggajian): data 120/menit per IP; permintaan token 10/menit per IP (rem brute force).
        RateLimiter::for('siap-api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('siap-token', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        // API SIKEU (keuangan): satu snapshot = satu panggilan per cabang jurnal (±200) → 600/menit per IP; token 10/menit.
        RateLimiter::for('sikeu-api', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
        RateLimiter::for('sikeu-token', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Gate aksi yang dibatasi — daftar role dari SATU sumber berklaster
        // (App\Support\AksiRole). Ubah role cukup di file itu; nama Gate di bawah
        // yang dipakai blade (@can) & server (->can()) tidak perlu ikut berubah.
        // Permission halaman `{route}.{lihat|tambah|ubah|hapus}` (mis. @can('master.poli.hapus'),
        // #[Authorize('master.poli.hapus')]) lewat aturan yang sama dengan penjaga URL —
        // termasuk cadangan roles AppMenu bila `akses:sinkron` belum dijalankan.
        // Ability lain → null (diteruskan ke Gate biasa).
        Gate::before(fn ($user, string $ability) => AksesHalaman::aturAbility($user, $ability));

        // Aksi khusus: Gate = permission bernama sama, dicentang per role di Role Control
        // (kolom Khusus). Katalog & role awal di blok program (AksiRole::khusus()); cadangan role bila
        // permission belum dibuat `akses:sinkron`. Lihat AksesHalaman::bolehKhusus().
        foreach (array_keys(AksiRole::khusus()) as $namaAksi) {
            Gate::define($namaAksi, fn ($user) => AksesHalaman::bolehKhusus($user, $namaAksi));
        }

        // Pintu modal: listener #[On] modal bersama dijaga #[Authorize('pintu.<event>')] —
        // lolos bila punya salah satu hak pembukanya (katalog AksesHalaman::pintu()).
        foreach (array_keys(AksesHalaman::pintu()) as $eventPintu) {
            Gate::define('pintu.' . $eventPintu, fn ($user) => AksesHalaman::gatePintu($user, $eventPintu));
        }

        Gate::define('emr.penunjangLihat', fn ($user) => $user->hasAnyRole(AksiRole::EMR_PENUNJANG_LIHAT));










        // Blade directive untuk render path TTD user (dua format kolom
        // myuser_ttd_image: filename saja vs legacy 'UserTtd/abc.png') —
        // logikanya terpusat di App\Support\TtdUser.
        // Pemakaian: <img src="@ttdSrc($user->myuser_ttd_image)" />
        Blade::directive('ttdSrc', function ($expression) {
            return "<?php echo \\App\\Support\\TtdUser::pathWeb($expression); ?>";
        });

        // Share $sidebarMenus (grouped + filtered by user role) ke sidebar layout.
        // Tidak query DB di guest pages — guard via auth check.
        View::composer('layouts.app-sidebar', function ($view) {
            $view->with('sidebarMenus', AppMenu::grouped(auth()->user()));
        });
    }
}
