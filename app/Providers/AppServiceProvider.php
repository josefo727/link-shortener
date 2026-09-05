<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('database.allow_disabled_pk')) {
            $this->allowDisabledPk();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Adjusts the MySQL session configuration to allow or require primary keys during migrations.
     *
     * This method listens for migration start and end events to dynamically toggle
     * the `sql_require_primary_key` session setting in MySQL. The statement is MySQL-only
     * syntax, so each listener re-checks the active connection's driver at event time and
     * no-ops on any other driver (see ADR 0001) rather than relying solely on the
     * ALLOW_DISABLED_PK env gate above.
     */
    private function allowDisabledPk(): void
    {
        Event::listen(MigrationsStarted::class, function (): void {
            $this->toggleMysqlPrimaryKeyRequirement(required: false);
        });

        Event::listen(MigrationsEnded::class, function (): void {
            $this->toggleMysqlPrimaryKeyRequirement(required: true);
        });
    }

    /**
     * Toggles MySQL's sql_require_primary_key session setting, a no-op on any other driver.
     */
    private function toggleMysqlPrimaryKeyRequirement(bool $required): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        $connection->statement('SET SESSION sql_require_primary_key='.($required ? '1' : '0'));
    }
}
