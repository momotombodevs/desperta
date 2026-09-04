<?php

namespace Momotombo\NativePHPAlarms;

use Illuminate\Support\ServiceProvider;
use Momotombo\NativePHPAlarms\Bridge\NativeAlarmBridge;
use Momotombo\NativePHPAlarms\Bridge\NativePHPAlarmBridge;

final class AlarmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NativeAlarmBridge::class, NativePHPAlarmBridge::class);
        $this->app->singleton(AlarmScheduler::class);
    }
}
