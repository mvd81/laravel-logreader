<?php

namespace Mvd81\LaravelLogreader;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Mvd81\LaravelLogreader\Livewire\LogViewer;

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

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/logreader'),
        ], 'logreader-views');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'logreader');

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        if (class_exists(Livewire::class)) {
            Livewire::component('logreader-log-viewer', LogViewer::class);
        }
    }
}
