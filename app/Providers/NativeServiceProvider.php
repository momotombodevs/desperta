<?php

namespace App\Providers;

use Donmanueldev\NativephpCharts\NativePHPChartsServiceProvider;
use Illuminate\Support\ServiceProvider;
use Momotombo\NativePHPAlarms\AlarmServiceProvider;
use Momotombo\NativephpAppearance\AppearanceServiceProvider;
use Native\Mobile\Providers\BrowserServiceProvider;
use Native\Mobile\UI\NativeUIServiceProvider;
use Unloc\NativephpEnhancedSplash\NativephpEnhancedSplashServiceProvider;
use Unloc\NativephpSvgComponent\SvgServiceProvider;
use Victorycodedev\ToastKit\ToastKitServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * The NativePHP plugins to enable.
     *
     * Only plugins listed here will be compiled into your native builds.
     * This is a security measure to prevent transitive dependencies from
     * automatically registering plugins without your explicit consent.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            NativeUIServiceProvider::class,
            BrowserServiceProvider::class,
            AlarmServiceProvider::class,
            AppearanceServiceProvider::class,
            ToastKitServiceProvider::class,
            NativePHPChartsServiceProvider::class,
            SvgServiceProvider::class,
            NativephpEnhancedSplashServiceProvider::class,

        ];
    }
}
