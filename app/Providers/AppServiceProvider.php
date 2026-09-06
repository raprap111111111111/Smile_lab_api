<?php

namespace App\Providers;

use App\Domain\Auth\Repositories\UserRepository;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Branch\Services\BranchScope;
use App\Domain\Inventories\DTOs\InventorySettings;
use App\Domain\Settings\DTOs\AppointmentSettings;
use App\Domain\Settings\Services\SettingService;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Reverb\ApplicationManagerServiceProvider;
use Laravel\Reverb\ReverbServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        // Scoped (per request), not singleton: settings edits must be visible
        // on the next request, and queue workers must re-read between jobs.
        $this->app->scoped(
            AppointmentSettings::class,
            fn($app) => AppointmentSettings::make($app->make(SettingService::class)),
        );

        $this->app->scoped(
            InventorySettings::class,
            fn($app) => InventorySettings::make($app->make(SettingService::class)),
        );

        // Scoped for the same reason: BranchScope memoises a user's branch
        // memberships, and a queue worker must not carry one job's answer into
        // the next.
        $this->app->scoped(BranchScope::class);

        // Reverb is excluded from package auto-discovery (composer.json
        // `extra.laravel.dont-discover`) and registered from here instead.
        //
        // ReverbServiceProvider::register() calls DevCommands::artisan() to add
        // `reverb:start` to `php artisan dev`. Laravel 13's
        // DevCommands::preventVendorRegistration() walks the backtrace and throws
        // unless one frame lives outside vendor/ — so when Reverb self-registers
        // via auto-discovery, every artisan command dies with "DevCommands should
        // be registered in application code, not within vendor packages".
        // Registering here puts this file in that backtrace, which is exactly the
        // application-code frame the guard looks for.
        $this->app->register(ApplicationManagerServiceProvider::class);
        $this->app->register(ReverbServiceProvider::class);
    }

    public function boot(): void
    {
        // `php artisan dev` runs its panes under a kill-others supervisor, and
        // Pail hard-requires ext-pcntl, which does not exist on Windows. Without
        // this the logs pane throws on startup and takes the API, Reverb, queue
        // and vite down with it — `php artisan dev` is unusable otherwise.
        // Use `php artisan pail` directly on a platform that has pcntl.
        if (PHP_OS_FAMILY === 'Windows') {
            DevCommands::except('logs');
        }

        // `artisan serve` blanks every environment variable that is not on its
        // passthrough allowlist. On Windows that includes TMP/TEMP, which PHP
        // needs to create the temporary file for an uploaded file — without
        // them every multipart upload dies at request startup with
        // "File upload error - unable to create a temporary file".
        if (PHP_OS_FAMILY === 'Windows') {
            ServeCommand::$passthroughVariables[] = 'TMP';
            ServeCommand::$passthroughVariables[] = 'TEMP';
        }

        // Super-admin bypass
        Gate::before(function ($user, $ability) {
            return $user->hasAnyRole(['super-admin', 'superadmin']) ? true : null;
        });

        // ─── Token Lifetimes ──────────────────────────────────────
        if (app()->environment('production')) {
            // Production: short-lived access, long-lived refresh
            Passport::tokensExpireIn(now()->addMinutes(60));           // 1 hour
            Passport::refreshTokensExpireIn(now()->addDays(30));       // 30 days
            Passport::personalAccessTokensExpireIn(now()->addDays(7)); // 7 days
        } else {
            // Development: long lifetimes so you don't get logged out
            Passport::tokensExpireIn(now()->addYear());
            Passport::refreshTokensExpireIn(now()->addYears(2));
            Passport::personalAccessTokensExpireIn(now()->addYear());
        }
    }
}