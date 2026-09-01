<?php

namespace App\Providers;

use App\Contracts\AnnouncementDrafter;
use App\Contracts\CommentWriter;
use App\Contracts\ImportHeaderMapper;
use App\Services\Ai\DeterministicAnnouncementDrafter;
use App\Services\Import\DeterministicHeaderMapper;
use App\Services\Import\HttpHeaderMapper;
use App\Services\Ai\DeterministicCommentWriter;
use App\Services\Ai\HttpAnnouncementDrafter;
use App\Services\Ai\HttpCommentWriter;
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

        /*
        | Report-card comment writer. The deterministic writer is always bound
        | as itself so it stays reachable as the fallback; the contract resolves
        | to whichever driver is configured. `http` is only ever chosen when AI
        | is enabled and keyed — CommentService re-checks the plan flag, so a
        | misconfigured deployment degrades rather than failing.
        */
        $this->app->singleton(DeterministicCommentWriter::class);

        $this->app->bind(CommentWriter::class, function ($app) {
            $driver = config('ai.driver', 'deterministic');
            $ready = config('ai.enabled') && config('ai.key') && config('ai.model');

            return $driver === 'http' && $ready
                ? $app->make(HttpCommentWriter::class)
                : $app->make(DeterministicCommentWriter::class);
        });

        /*
        | Announcement drafting, bound the same way and for the same reasons.
        | The two blocks must stay in step: the same three config conditions
        | decide whether either may reach an external model.
        */
        $this->app->singleton(DeterministicAnnouncementDrafter::class);

        $this->app->bind(AnnouncementDrafter::class, function ($app) {
            $driver = config('ai.driver', 'deterministic');
            $ready = config('ai.enabled') && config('ai.key') && config('ai.model');

            return $driver === 'http' && $ready
                ? $app->make(HttpAnnouncementDrafter::class)
                : $app->make(DeterministicAnnouncementDrafter::class);
        });

        /*
        | CSV header mapping. Bound the same way again, and for the same reason:
        | the rule table is the default, the provider only ever runs when AI is
        | enabled and keyed, and a provider failure falls back to the rules.
        */
        $this->app->singleton(DeterministicHeaderMapper::class);

        $this->app->bind(ImportHeaderMapper::class, function ($app) {
            $driver = config('ai.driver', 'deterministic');
            $ready = config('ai.enabled') && config('ai.key') && config('ai.model');

            return $driver === 'http' && $ready
                ? $app->make(HttpHeaderMapper::class)
                : $app->make(DeterministicHeaderMapper::class);
        });
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
