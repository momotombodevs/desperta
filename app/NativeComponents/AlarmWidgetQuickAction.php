<?php

namespace App\NativeComponents;

use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Models\Alarm;
use Illuminate\View\View;
use Momotombo\NativePHPAlarms\Exceptions\AlarmException;
use Native\Mobile\Edge\NativeComponent;
use Victorycodedev\ToastKit\Facades\Toast;

final class AlarmWidgetQuickAction extends NativeComponent
{
    public function mount(): void
    {
        $alarm = Alarm::query()->find($this->param('alarmId'));
        if ($alarm === null) {
            $this->showError(__('app.widget_alarm_missing'));
            $this->replace('/');

            return;
        }

        $scheduler = app(NativeAlarmScheduler::class);
        if ($alarm->enabled) {
            try {
                $scheduler->cancel($alarm->id);
                app(AlarmExecutionLifecycle::class)->cancelOpen($alarm);
                $alarm->update(['enabled' => false, 'scheduling_status' => 'not_scheduled']);
                Toast::success(__('app.widget_alarm_paused'))->position('top')->duration(3000)->show();
            } catch (AlarmException $exception) {
                report($exception);
                $this->showError(__('app.widget_alarm_error'));
            }

            $this->replace('/');

            return;
        }

        if (! $scheduler->canScheduleExactly() || ! $scheduler->canPresentWhileLocked() || ! $scheduler->canPostNotifications()) {
            $this->showError(__('app.widget_alarm_permission'));
            $this->replace("/alarms/{$alarm->id}/edit");

            return;
        }

        try {
            $schedule = app(AlarmExecutionLifecycle::class)->scheduleFor($alarm);
            $scheduler->schedule($schedule);
            $alarm->update(['enabled' => true, 'scheduling_status' => 'scheduled']);
            Toast::success(__('app.widget_alarm_enabled'))->position('top')->duration(3000)->show();
        } catch (AlarmException $exception) {
            report($exception);
            app(AlarmExecutionLifecycle::class)->cancelOpen($alarm);
            $this->showError(__('app.widget_alarm_error'));
        } catch (\InvalidArgumentException $exception) {
            $this->showError(__('app.alarm_date_must_be_future'));
            $this->replace("/alarms/{$alarm->id}/edit");

            return;
        }

        $this->replace('/');
    }

    public function render(): View
    {
        return view('native.alarm-widget-quick-action');
    }

    private function showError(string $message): void
    {
        Toast::error($message)->position('top')->duration(4000)->show();
    }
}
