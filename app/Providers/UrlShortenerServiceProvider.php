<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CacheServiceInterface;
use App\Contracts\CodeGeneratorInterface;
use App\Contracts\UrlValidatorInterface;
use App\Services\CacheService;
use App\Services\CodeGeneratorService;
use App\Services\UrlValidatorService;
use Illuminate\Support\ServiceProvider;

final class UrlShortenerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(CodeGeneratorInterface::class, CodeGeneratorService::class);
        $this->app->bind(UrlValidatorInterface::class, UrlValidatorService::class);
        $this->app->bind(CacheServiceInterface::class, CacheService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
