<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Re-registers AppServiceProvider's allowDisabledPk() listeners fresh, so each test controls
 * config('database.allow_disabled_pk') independently of whatever the app already registered
 * at boot.
 */
function registerAllowDisabledPkForTest(): void
{
    Config::set('database.allow_disabled_pk', true);
    (new AppServiceProvider(app()))->register();
}

it('does not run the MySQL-only statement when the active connection driver is sqlite', function (): void {
    // The test suite's real default connection is sqlite (phpunit.xml). If the guard is absent,
    // dispatching these events throws — sqlite has no "SET SESSION" syntax.
    registerAllowDisabledPkForTest();

    expect(fn () => Event::dispatch(new MigrationsStarted('up')))->not->toThrow(Throwable::class);
    expect(fn () => Event::dispatch(new MigrationsEnded('up')))->not->toThrow(Throwable::class);
});

it('does not run the MySQL-only statement when the active connection driver is pgsql', function (): void {
    registerAllowDisabledPkForTest();

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('pgsql');
    $connection->shouldReceive('statement')->never();
    DB::shouldReceive('connection')->andReturn($connection);

    Event::dispatch(new MigrationsStarted('up'));
    Event::dispatch(new MigrationsEnded('up'));
});

it('runs the MySQL-only statement when the active connection driver is mysql', function (): void {
    registerAllowDisabledPkForTest();

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('statement')->once()->with('SET SESSION sql_require_primary_key=0');
    $connection->shouldReceive('statement')->once()->with('SET SESSION sql_require_primary_key=1');
    DB::shouldReceive('connection')->andReturn($connection);

    Event::dispatch(new MigrationsStarted('up'));
    Event::dispatch(new MigrationsEnded('up'));
});

it('registers no listener at all when ALLOW_DISABLED_PK is false', function (): void {
    Config::set('database.allow_disabled_pk', false);

    $listenersBefore = count(Event::getListeners(MigrationsStarted::class));

    (new AppServiceProvider(app()))->register();

    expect(Event::getListeners(MigrationsStarted::class))->toHaveCount($listenersBefore);
});
