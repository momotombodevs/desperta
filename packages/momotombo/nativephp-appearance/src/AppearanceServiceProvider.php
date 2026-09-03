<?php

namespace Momotombo\NativephpAppearance;

use Illuminate\Support\ServiceProvider;
use Momotombo\NativephpAppearance\Commands\CopyAssetsCommand;

class AppearanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Appearance::class, function () {
            return new Appearance;
        });
    }

    public function boot(): void
    {
        // Register plugin hook commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CopyAssetsCommand::class,
            ]);
        }
    }
}
