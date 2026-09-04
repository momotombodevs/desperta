<?php

namespace App\NativeComponents;

use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Challenges\ChallengeDifficulty;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;
use Momotombo\NativePHPAlarms\Exceptions\AlarmException;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Transition;
use Victorycodedev\ToastKit\Facades\Toast;

class AlarmEditor extends NativeComponent
{
    use ResumesActiveAlarm;

    public string $alarmId = '';

    public bool $isEditing = false;

    public bool $awaitingPermission = false;

    public bool $resumeAfterExactAlarmPermission = false;

    public bool $resumeAfterFullScreenAlarmPermission = false;

    public bool $cancelExistingSchedule = false;

    public string $notificationPermissionRequestId = '';

    public string $time = '07:00';

    public string $label = '';

    public bool $monday = true;

    public bool $tuesday = true;

    public bool $wednesday = true;

    public bool $thursday = true;

    public bool $friday = true;

    public bool $saturday = false;

    public bool $sunday = false;

    public bool $vibration = true;

    public bool $snoozeEnabled = true;

    public int $snoozeMinutes = 5;

    public bool $enabled = true;

    public string $difficulty = 'normal';

    public string $difficultyDisplay = 'Normal';

    public function mount(): void
    {
        $preferences = app(AppPreferences::class);
        $preferences->applyLanguage();
        $this->difficultyDisplay = $this->localizedDifficulty($this->difficulty);
        $alarmId = (string) $this->param('alarm', '');

        if ($alarmId === '') {
            return;
        }

        $alarm = Alarm::query()->findOrFail($alarmId);

        $this->alarmId = $alarm->id;
        $this->isEditing = true;
        $this->time = $alarm->time;
        $this->label = $alarm->label;
        $this->monday = in_array(1, $alarm->weekdays, true);
        $this->tuesday = in_array(2, $alarm->weekdays, true);
        $this->wednesday = in_array(3, $alarm->weekdays, true);
        $this->thursday = in_array(4, $alarm->weekdays, true);
        $this->friday = in_array(5, $alarm->weekdays, true);
        $this->saturday = in_array(6, $alarm->weekdays, true);
        $this->sunday = in_array(7, $alarm->weekdays, true);
        $this->vibration = $alarm->vibration;
        $this->snoozeEnabled = $alarm->snooze_enabled;
        $this->snoozeMinutes = $alarm->snoozeMinutes();
        $this->enabled = $alarm->enabled;
        $this->difficulty = $alarm->difficulty;
        $this->difficultyDisplay = $this->localizedDifficulty($this->difficulty);
    }

    public function updatedDifficultyDisplay(): void
    {
        $this->difficulty = $this->storedDifficulty($this->difficultyDisplay);
    }

    public function save(): void
    {
        if ($this->awaitingPermission) {
            return;
        }

        $scheduler = app(NativeAlarmScheduler::class);
        $alarm = $this->alarmId === '' ? new Alarm : Alarm::query()->findOrFail($this->alarmId);
        $wasScheduled = $alarm->exists && $alarm->scheduling_status === 'scheduled';

        $alarm->fill([
            'time' => $this->time,
            'label' => $this->label,
            'weekdays' => $this->selectedWeekdays(),
            'vibration' => $this->vibration,
            'snooze_enabled' => $this->snoozeEnabled,
            'snooze_minutes' => $this->validSnoozeMinutes(),
            'difficulty' => $this->difficulty,
            'enabled' => $this->enabled,
            'scheduling_status' => $this->enabled ? 'pending' : 'not_scheduled',
        ]);
        $alarm->save();
        $this->alarmId = $alarm->id;
        $this->isEditing = true;
        $this->cancelExistingSchedule = $wasScheduled;

        if (! $alarm->enabled) {
            if ($wasScheduled) {
                $scheduler->cancel($alarm->id);
            }

            app(AlarmExecutionLifecycle::class)->cancelOpen($alarm);

            $this->replace('/')->transition(Transition::Fade);

            return;
        }

        $this->continueScheduling($alarm);
    }

    public function cancel(): void
    {
        $this->replace('/')->transition(Transition::Fade);
    }

    public function onResume(): void
    {
        $scheduler = app(NativeAlarmScheduler::class);

        if ($this->resumeAfterExactAlarmPermission) {
            $this->resumeAfterExactAlarmPermission = false;

            if (! $scheduler->canScheduleExactly()) {
                $this->showErrorToast(__('app.exact_alarm_permission_denied'));

                return;
            }

            $this->continueScheduling(Alarm::query()->findOrFail($this->alarmId));

            return;
        }

        if (! $this->resumeAfterFullScreenAlarmPermission) {
            return;
        }

        $this->resumeAfterFullScreenAlarmPermission = false;

        if (! $scheduler->canPresentWhileLocked()) {
            $this->showErrorToast(__('app.scheduling_error'));

            return;
        }

        $this->continueScheduling(Alarm::query()->findOrFail($this->alarmId));
    }

    #[On(NotificationAuthorizationChanged::class)]
    public function handleNotificationAuthorizationChanged(bool $granted, string $requestId): void
    {
        if (! $this->awaitingPermission || ! hash_equals($this->notificationPermissionRequestId, $requestId)) {
            return;
        }

        $this->awaitingPermission = false;
        $this->notificationPermissionRequestId = '';

        if (! $granted) {
            $this->showErrorToast(__('app.notification_permission_denied'));

            return;
        }

        $this->continueScheduling(Alarm::query()->findOrFail($this->alarmId));
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
                $this->awaitingPermission = true;
                $this->notificationPermissionRequestId = (string) Str::uuid();
                $scheduler->requestNotificationPermission($this->notificationPermissionRequestId);

                return;
            }

            if ($this->cancelExistingSchedule) {
                $scheduler->cancel($alarm->id);
                app(AlarmExecutionLifecycle::class)->cancelOpen($alarm);
            }

            $schedule = app(AlarmExecutionLifecycle::class)->scheduleFor($alarm);
            $scheduler->schedule($schedule);

            $alarm->update(['scheduling_status' => 'scheduled']);
        } catch (AlarmException $exception) {
            $this->awaitingPermission = false;
            $this->notificationPermissionRequestId = '';
            report($exception);
            $this->showErrorToast($exception->getMessage() ?: __('app.scheduling_error'));

            return;
        }

        $this->cancelExistingSchedule = false;
        Toast::success(__('app.alarm_scheduled'))
            ->position('top')
            ->animation('spring')
            ->duration(3000)
            ->show();

        $this->replace('/')->transition(Transition::Fade);
    }

    private function showErrorToast(string $message): void
    {
        Toast::error($message)
            ->position('top')
            ->animation('snap')
            ->duration(4000)
            ->show();
    }

    /** @return list<int> */
    private function selectedWeekdays(): array
    {
        return collect([
            1 => $this->monday,
            2 => $this->tuesday,
            3 => $this->wednesday,
            4 => $this->thursday,
            5 => $this->friday,
            6 => $this->saturday,
            7 => $this->sunday,
        ])->filter()->keys()->map(fn (int $weekday): int => $weekday)->values()->all();
    }

    private function localizedDifficulty(string $difficulty): string
    {
        return __('app.'.ChallengeDifficulty::fromStored($difficulty)->value);
    }

    private function storedDifficulty(string $difficulty): string
    {
        return ChallengeDifficulty::fromStored($difficulty)->value;
    }

    private function validSnoozeMinutes(): int
    {
        return in_array($this->snoozeMinutes, [5, 10, 15], true) ? $this->snoozeMinutes : 5;
    }

    public function render(): View
    {
        return view('native.alarm-editor');
    }
}
