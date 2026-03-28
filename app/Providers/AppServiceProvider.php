<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
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
        $request = $this->app['request'];
        $forwardedProto = strtolower((string) $request->header('X-Forwarded-Proto', ''));
        $hasAzureSslHeader = $request->header('X-ARR-SSL') !== null;
        $configuredAppUrl = strtolower((string) config('app.url'));
        $shouldForceHttps =
            $this->app->environment('production') ||
            str_starts_with($configuredAppUrl, 'https://') ||
            $forwardedProto === 'https' ||
            $hasAzureSslHeader;

        if ($shouldForceHttps) {
            $forcedRootUrl = 'https://'.$request->getHttpHost();

            URL::forceRootUrl($forcedRootUrl);
            URL::forceScheme('https');
            Paginator::currentPathResolver(fn () => url($request->path()));
        }

        Vite::prefetch(concurrency: 3);
    }
}
