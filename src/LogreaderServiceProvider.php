<?php

namespace Mvd81\LaravelLogreader;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Mvd81\LaravelLogreader\Http\Middleware\AddRequestContext;

class LogreaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/logreader.php',
            'logreader'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/logreader.php' => config_path('logreader.php'),
        ], 'logreader-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        if (config('logreader.context.enabled')) {
            $this->app->make(Kernel::class)->pushMiddleware(AddRequestContext::class);
        }
    }
}
