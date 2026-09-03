<?php

namespace App\Providers;

use App\Application\AlarmScheduling\NativeAlarmGateway;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Infrastructure\NativeAlarm\AndroidNativeAlarmScheduler;
use App\Infrastructure\NativeAlarm\NativePHPAlarmGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NativeAlarmGateway::class, NativePHPAlarmGateway::class);
        $this->app->singleton(NativeAlarmScheduler::class, AndroidNativeAlarmScheduler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
