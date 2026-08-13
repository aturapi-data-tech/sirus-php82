<?php

namespace App\Providers;

use App\Services\AppMenu;
use App\Support\AksiRole;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        // Gate aksi yang dibatasi — daftar role dari SATU sumber berklaster
        // (App\Support\AksiRole). Ubah role cukup di file itu; nama Gate di bawah
        // yang dipakai blade (@can) & server (->can()) tidak perlu ikut berubah.
        Gate::define('dokumen.hapus', fn ($user) => $user->hasAnyRole(AksiRole::DOKUMEN_HAPUS));
        Gate::define('dokumen.bukaKunci', fn ($user) => $user->hasAnyRole(AksiRole::DOKUMEN_BUKA_KUNCI));

        Gate::define('emr.logAktivitas', fn ($user) => $user->hasAnyRole(AksiRole::EMR_LOG_AKTIVITAS));
        Gate::define('emr.cetakEresep', fn ($user) => $user->hasAnyRole(AksiRole::EMR_CETAK_ERESEP));

        Gate::define('idrg.kirim', fn ($user) => $user->hasAnyRole(AksiRole::IDRG_KIRIM));
        Gate::define('satusehat.kirim', fn ($user) => $user->hasAnyRole(AksiRole::SATUSEHAT_KIRIM));

        Gate::define('transaksi.batalPenerimaan', fn ($user) => $user->hasAnyRole(AksiRole::TRANSAKSI_BATAL_PENERIMAAN));
        Gate::define('ri.pindahKamar', fn ($user) => $user->hasAnyRole(AksiRole::RI_PINDAH_KAMAR));

        Gate::define('gudang.opnameMedis', fn ($user) => $user->hasAnyRole(AksiRole::GUDANG_OPNAME_MEDIS));
        Gate::define('gudang.opnameNonMedis', fn ($user) => $user->hasAnyRole(AksiRole::GUDANG_OPNAME_NONMEDIS));

        // Blade directive untuk render path TTD user.
        // - Standar baru: DB simpan filename saja (mis: 08052026081302.png)
        //   → prepend 'storage/UserTtd/'
        // - Legacy: DB simpan full path (mis: 'UserTtd/abc.png')
        //   → pakai apa adanya dengan prefix 'storage/'
        // Pemakaian: <img src="@ttdSrc($user->myuser_ttd_image)" />
        Blade::directive('ttdSrc', function ($expression) {
            return "<?php echo (function (\$v) { return empty(\$v) ? '' : 'storage/' . (str_contains(\$v, '/') ? \$v : 'UserTtd/' . \$v); })($expression); ?>";
        });

        // Share $sidebarMenus (grouped + filtered by user role) ke sidebar layout.
        // Tidak query DB di guest pages — guard via auth check.
        View::composer('layouts.app-sidebar', function ($view) {
            $roles = auth()->check()
                ? auth()->user()->getRoleNames()->map(fn($r) => trim(strtolower($r)))->values()->toArray()
                : [];

            $view->with('sidebarMenus', AppMenu::grouped($roles));
        });
    }
}
