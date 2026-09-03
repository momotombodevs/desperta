<?php

namespace App\NativeComponents;

use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;
use Momotombo\NativePHPAlarms\Exceptions\AlarmException;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\Transition;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use Victorycodedev\ToastKit\Facades\Toast;

class Home extends NativeComponent
{
    /** @var list<string> */
    private const array AppearancePreferences = ['system', 'light', 'dark'];

    public bool $settingsOpen = false;

    public bool $historyOpen = false;

    public bool $emptyStateVisible = false;

    public bool $awaitingAlarmActivationPermission = false;

    public bool $resumeAfterExactAlarmPermission = false;

    public bool $resumeAfterFullScreenAlarmPermission = false;

    public bool $cancelExistingSchedule = false;

    public string $activationAlarmId = '';

    public string $notificationPermissionRequestId = '';

    public string $appearancePreference = 'system';

    public int $appearanceSelection = 0;

    public string $languagePreference = 'es_NI';

    public string $challengeThemePreference = 'nicaragua';

    public function mount(): void
    {
        $preferences = app(AppPreferences::class);
        $preferences->applyLanguage();
        $preferences->applyAppearance();
        $this->appearancePreference = $preferences->appearance();
        $this->languagePreference = $preferences->language();
        $this->challengeThemePreference = $preferences->challengeTheme();
        $this->appearanceSelection = $this->selectionFor(self::AppearancePreferences, $this->appearancePreference);
        $this->emptyStateVisible = Alarm::query()->doesntExist();

        $this->resumeActiveChallenge();
    }

    private function resumeActiveChallenge(): void
    {
        try {
            $alarmId = app(NativeAlarmScheduler::class)->activeRingingAlarmId();
        } catch (NativeAlarmSchedulingFailed $exception) {
            report($exception);

            return;
        }

        if ($alarmId === null) {
            return;
        }

        $this->replace('/challenge', ['alarmId' => $alarmId]);
    }

    public function createAlarm(): void
    {
        $this->navigate('/alarms/new')->transition(Transition::SlideFromBottom);
    }

    public function editAlarm(string $alarmId): void
    {
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

            return;
        }

        $this->activationAlarmId = $alarm->id;
        $this->cancelExistingSchedule = $wasScheduled;
        $this->continueScheduling($alarm);
    }

    public function onResume(): void
    {
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

    public function openSettings(): void
    {
        $this->settingsOpen = true;
    }

    public function closeSettings(): void
    {
        $this->settingsOpen = false;
    }

    public function openHistory(): void
    {
        $this->historyOpen = true;
    }

    public function openHistoryFromSettings(): void
    {
        $this->settingsOpen = false;
        $this->historyOpen = true;
    }

    public function closeHistory(): void
    {
        $this->historyOpen = false;
    }

    public function updatedAppearancePreference(): void
    {
        app(AppPreferences::class)->setAppearance($this->appearancePreference);
        $this->appearanceSelection = $this->selectionFor(self::AppearancePreferences, $this->appearancePreference);
    }

    public function updatedAppearanceSelection(): void
    {
        $this->appearancePreference = self::AppearancePreferences[$this->appearanceSelection] ?? self::AppearancePreferences[0];

        app(AppPreferences::class)->setAppearance($this->appearancePreference);
    }

    public function updatedLanguagePreference(): void
    {
        app(AppPreferences::class)->setLanguage($this->languagePreference);
    }

    public function selectLanguage(string $language): void
    {
        $this->languagePreference = $language;

        app(AppPreferences::class)->setLanguage($this->languagePreference);
    }

    public function selectChallengeTheme(string $label): void
    {
        $theme = array_search($label, $this->challengeThemeOptions(), true);

        if ($theme === false) {
            return;
        }

        $this->challengeThemePreference = $theme;

        app(AppPreferences::class)->setChallengeTheme($theme);
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
        return $this->alarms->firstWhere('enabled', true);
    }

    /** @return Collection<int, AlarmExecution> */
    #[Computed]
    public function recentExecutions(): Collection
    {
        return AlarmExecution::query()
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('native.home');
    }

    /** @param list<string> $options */
    private function selectionFor(array $options, string $value): int
    {
        $selection = array_search($value, $options, true);

        return $selection === false ? 0 : $selection;
    }

    /** @return array<string, string> */
    private function challengeThemeOptions(): array
    {
        return [
            'nicaragua' => __('challenges.nicaragua.name'),
            'math' => __('challenges.math.name'),
            'general_knowledge' => __('challenges.general_knowledge.name'),
        ];
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
