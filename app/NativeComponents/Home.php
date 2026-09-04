<?php

namespace App\NativeComponents;

use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;
use Momotombo\NativePHPAlarms\Exceptions\AlarmException;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Transition;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use Victorycodedev\ToastKit\Facades\Toast;

class Home extends NativeComponent
{
    use ResumesActiveAlarm;

    public bool $emptyStateVisible = false;

    public bool $awaitingAlarmActivationPermission = false;

    public bool $resumeAfterExactAlarmPermission = false;

    public bool $resumeAfterFullScreenAlarmPermission = false;

    public bool $cancelExistingSchedule = false;

    public string $activationAlarmId = '';

    public string $notificationPermissionRequestId = '';

    public function mount(): void
    {
        $preferences = app(AppPreferences::class);
        $preferences->applyLanguage();
        $preferences->applyAppearance();

        $this->emptyStateVisible = Alarm::query()->doesntExist();
        $this->resumeActiveAlarm();
    }

    public function createAlarm(): void
    {
        $this->navigate('/alarms/new')->transition(Transition::SlideFromBottom);
    }

    public function editAlarm(string $alarmId): void
    {
        $occurrence = $this->refreshActiveOccurrence();

        if ($occurrence?->alarmId === $alarmId) {
            $this->navigate('/challenge', [
                'alarmId' => $occurrence->alarmId,
                'executionId' => $occurrence->executionId,
                'scheduledFor' => $occurrence->scheduledFor,
            ]);

            return;
        }

        $this->navigate("/alarms/{$alarmId}/edit");
    }

    public function confirmDeleteAlarm(string $alarmId): void
    {
        Dialog::alert(
            __('app.delete_alarm'),
            __('app.delete_alarm_confirmation'),
            [
                ['label' => __('app.cancel'), 'style' => 'cancel'],
                ['label' => __('app.delete'), 'style' => 'destructive'],
            ],
        )->id("delete-alarm-{$alarmId}")->show();
    }

    #[On(ButtonPressed::class)]
    public function handleAlertButton(int $index, string $label, ?string $id = null): void
    {
        if ($index !== 1 || $id === null || ! Str::startsWith($id, 'delete-alarm-')) {
            return;
        }

        $this->deleteAlarm(Str::after($id, 'delete-alarm-'));
    }

    public function deleteAlarm(string $alarmId): void
    {
        $alarm = Alarm::query()->findOrFail($alarmId);

        if ($alarm->scheduling_status === 'scheduled') {
            app(NativeAlarmScheduler::class)->cancel($alarm->id);
        }

        app(AlarmExecutionLifecycle::class)->cancelOpen($alarm);

        $alarm->delete();

        if ($this->activeAlarmId === $alarmId) {
            $this->activeAlarmId = '';
        }

        $this->emptyStateVisible = true;
    }

    public function toggleAlarm(string $alarmId, bool $enabled): void
    {
        if ($this->awaitingAlarmActivationPermission) {
            return;
        }

        $scheduler = app(NativeAlarmScheduler::class);
        $alarm = Alarm::query()->findOrFail($alarmId);
        $wasScheduled = $alarm->scheduling_status === 'scheduled';

        $alarm->update([
            'enabled' => $enabled,
            'scheduling_status' => $enabled ? 'pending' : 'not_scheduled',
        ]);

        if (! $enabled) {
            if ($wasScheduled) {
                $scheduler->cancel($alarm->id);
            }

            app(AlarmExecutionLifecycle::class)->cancelOpen($alarm);

            if ($this->activeAlarmId === $alarmId) {
                $this->activeAlarmId = '';
            }

            return;
        }

        $this->activationAlarmId = $alarm->id;
        $this->cancelExistingSchedule = $wasScheduled;
        $this->continueScheduling($alarm);
    }

    public function onResume(): void
    {
        if ($this->resumeActiveAlarm()) {
            return;
        }

        if ($this->activationAlarmId === '') {
            return;
        }

        $scheduler = app(NativeAlarmScheduler::class);

        if ($this->resumeAfterExactAlarmPermission) {
            $this->resumeAfterExactAlarmPermission = false;

            if (! $scheduler->canScheduleExactly()) {
                $this->showSchedulingError(__('app.exact_alarm_permission_denied'));

                return;
            }

            $this->continueScheduling(Alarm::query()->findOrFail($this->activationAlarmId));

            return;
        }

        if (! $this->resumeAfterFullScreenAlarmPermission) {
            return;
        }

        $this->resumeAfterFullScreenAlarmPermission = false;

        if (! $scheduler->canPresentWhileLocked()) {
            $this->showSchedulingError(__('app.scheduling_error'));

            return;
        }

        $this->continueScheduling(Alarm::query()->findOrFail($this->activationAlarmId));
    }

    #[On(NotificationAuthorizationChanged::class)]
    public function handleNotificationAuthorizationChanged(bool $granted, string $requestId): void
    {
        if (! $this->awaitingAlarmActivationPermission || ! hash_equals($this->notificationPermissionRequestId, $requestId)) {
            return;
        }

        $this->awaitingAlarmActivationPermission = false;
        $this->notificationPermissionRequestId = '';

        if (! $granted) {
            $this->showSchedulingError(__('app.notification_permission_denied'));

            return;
        }

        $this->continueScheduling(Alarm::query()->findOrFail($this->activationAlarmId));
    }

    /** @return Collection<int, Alarm> */
    #[Computed]
    public function alarms(): Collection
    {
        return Alarm::query()->orderBy('time')->get();
    }

    #[Computed]
    public function nextAlarm(): ?Alarm
    {
        return $this->alarms
            ->filter(fn (Alarm $alarm): bool => $alarm->enabled)
            ->sortBy(fn (Alarm $alarm): int => app(AlarmExecutionLifecycle::class)->nextScheduledFor($alarm)->getTimestamp())
            ->first();
    }

    public function render(): View
    {
        return view('native.home');
    }

    private function continueScheduling(Alarm $alarm): void
    {
        $scheduler = app(NativeAlarmScheduler::class);

        try {
            if (! $scheduler->canScheduleExactly()) {
                $this->resumeAfterExactAlarmPermission = true;
                $scheduler->requestExactAlarmPermission();

                return;
            }

            if (! $scheduler->canPresentWhileLocked()) {
                $this->resumeAfterFullScreenAlarmPermission = true;
                $scheduler->requestFullScreenAlarmPermission();

                return;
            }

            if (! $scheduler->canPostNotifications()) {
                $this->awaitingAlarmActivationPermission = true;
                $this->notificationPermissionRequestId = (string) Str::uuid();
                $scheduler->requestNotificationPermission($this->notificationPermissionRequestId);

                return;
            }

            if ($this->cancelExistingSchedule) {
                $scheduler->cancel($alarm->id);
            }

            $schedule = app(AlarmExecutionLifecycle::class)->scheduleFor($alarm);
            $scheduler->schedule($schedule);

            $alarm->update(['scheduling_status' => 'scheduled']);
        } catch (AlarmException $exception) {
            $this->awaitingAlarmActivationPermission = false;
            $this->notificationPermissionRequestId = '';
            report($exception);
            $this->showSchedulingError($exception->getMessage() ?: __('app.scheduling_error'));

            return;
        }

        $this->activationAlarmId = '';
        $this->cancelExistingSchedule = false;
        Toast::success(__('app.alarm_scheduled'))->position('top')->animation('spring')->duration(3000)->show();
    }

    private function showSchedulingError(string $message): void
    {
        Toast::error($message)->position('top')->animation('snap')->duration(4000)->show();
    }
}
