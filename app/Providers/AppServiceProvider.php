<?php

namespace App\Providers;

use App\File;
use App\Http\ViewComposers\AdminComposer;
use App\Policies\FilePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Role;
use App\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use League\Flysystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // must run before PassportServiceProvider::boot() (package providers
        // boot first): restores the first-party JSON API surface
        // (/oauth/clients, /oauth/tokens, /oauth/scopes,
        // /oauth/personal-access-tokens) that Passport 8 registered by
        // default and the admin passport pages (0-usage feature) call via
        // axios. Passport 13 gates these routes behind this flag.
        Passport::$registersJsonApiRoutes = true;

        // payment rail swap point (D18): midtrans primary, xendit to come
        $this->app->bind(
            \App\Services\Billing\Contracts\PaymentGateway::class,
            function () {
                return match (config('billing.gateway')) {
                    'xendit' => throw new \RuntimeException('Xendit gateway is not implemented yet (fallback rail).'),
                    default => new \App\Services\Billing\MidtransGateway(),
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerEvents();
        $this->registerViewComposers();
        $this->registerRateLimiters();
        $this->configurePassport();
        $this->registerFlysystemDrivers();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(File::class, FilePolicy::class);
    }

    protected function registerEvents(): void
    {
        // send the verification email whenever a new user registers,
        // mirroring the previous EventServiceProvider mapping
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
    }

    protected function registerViewComposers(): void
    {
        View::composer('drive.index', AdminComposer::class);
    }

    protected function registerRateLimiters(): void
    {
        // generic API limiter (was never enforced group-wide before;
        // keeping it opt-in via throttle:api)
        \Illuminate\Support\Facades\RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // chunked uploads are many tiny requests per file; a plain 60/min
        // limit (the previous setup) broke uploads of 100M+ files mid-flight
        \Illuminate\Support\Facades\RateLimiter::for('chunk', function (Request $request) {
            return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
        });
    }

    protected function configurePassport(): void
    {
        // keep the previous token lifetimes (1 day access, 2 day refresh)
        Passport::tokensExpireIn(now()->addDays(1));
        Passport::refreshTokensExpireIn(now()->addDays(2));
    }

    /**
     * Custom Flysystem 3 adapters are no longer auto-discovered from disk
     * names (the Laravel 5/6 "extending from config" era is gone), so the
     * legacy "dropbox" disk gets an explicit adapter registration here.
     */
    protected function registerFlysystemDrivers(): void
    {
        Storage::extend('dropbox', fn ($app, array $config) => new Filesystem(
            new \Spatie\FlysystemDropbox\DropboxAdapter(
                new \Spatie\Dropbox\Client($config['token']),
                $config['root'] ?? ''
            )
        ));
    }
}
