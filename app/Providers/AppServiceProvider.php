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
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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
        $this->registerPolicies();
        $this->registerEvents();
        $this->registerViewComposers();
        $this->registerRateLimiters();
        $this->configurePassport();
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
}
