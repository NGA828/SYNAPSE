<?php

namespace App\Providers;

use App\Services\Sms\SmsManager;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request lifecycle (safe under Octane).
        $this->app->scoped(TenantContext::class);

        // Single SMS manager; the driver itself is resolved per send.
        $this->app->singleton(SmsManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail loudly in development when a mass-assignment silently drops an
        // attribute (lazy loading stays allowed — the app relies on it).
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Signed links (document verification) must use the public URL.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
