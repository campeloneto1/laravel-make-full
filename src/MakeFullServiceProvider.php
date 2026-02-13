<?php

namespace Campelo\MakeFull;

use Campelo\MakeFull\Console\Commands\MakeFullCommand;
use Illuminate\Support\ServiceProvider;

class MakeFullServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/make-full.php', 'make-full');
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/config/make-full.php' => config_path('make-full.php'),
        ], 'make-full-config');

        // Publish stubs for customization
        $this->publishes([
            __DIR__ . '/Stubs/' => base_path('stubs/make-full/'),
        ], 'make-full-stubs');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeFullCommand::class,
            ]);
        }
    }
}
