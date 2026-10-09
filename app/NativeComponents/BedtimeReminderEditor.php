<?php

namespace App\NativeComponents;

use App\Application\Preferences\AppPreferences;
use App\Application\Preferences\BedtimeReminderSettings;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Momotombo\NativePHPAlarms\DTO\BedtimeReminderConfiguration;
use Momotombo\NativePHPAlarms\Enums\Weekday;
use Momotombo\NativePHPAlarms\Events\NotificationAuthorizationChanged;
use Momotombo\NativePHPAlarms\Exceptions\AlarmException;
use Momotombo\NativePHPAlarms\Facades\Alarm;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Victorycodedev\ToastKit\Facades\Toast;

final class BedtimeReminderEditor extends NativeComponent
{
    public bool $enabled = false;

    public string $time = '21:30';

    public bool $monday = true;

    public bool $tuesday = true;

    public bool $wednesday = true;

    public bool $thursday = true;

    public bool $friday = true;

    public bool $saturday = true;

    public bool $sunday = true;

    public bool $awaitingExactPermission = false;

    public bool $awaitingNotificationPermission = false;

    public string $notificationPermissionRequestId = '';

    public function mount(): void
    {
        app(AppPreferences::class)->applyLanguage();
        $settings = app(BedtimeReminderSettings::class)->get();
        $this->enabled = $settings['enabled'];
        $this->time = $settings['time'];
        $this->monday = in_array(1, $settings['weekdays'], true);
        $this->tuesday = in_array(2, $settings['weekdays'], true);
        $this->wednesday = in_array(3, $settings['weekdays'], true);
        $this->thursday = in_array(4, $settings['weekdays'], true);
        $this->friday = in_array(5, $settings['weekdays'], true);
        $this->saturday = in_array(6, $settings['weekdays'], true);
        $this->sunday = in_array(7, $settings['weekdays'], true);
    }

    public function save(): void
    {
        $settings = app(BedtimeReminderSettings::class);

        if (! $this->enabled) {
            try {
                Alarm::cancelBedtimeReminder();
                $settings->save(false, $this->time, $this->selectedWeekdays());
                $this->showSuccessToast();
            } catch (AlarmException $exception) {
                report($exception);
                $this->showErrorToast(__('app.bedtime_reminder_error'));
            }

            return;
        }

        if ($this->selectedWeekdays() === [] || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $this->time) !== 1) {
            $this->showErrorToast(__('app.bedtime_reminder_invalid'));

            return;
        }

        $this->scheduleAfterPermissions();
    }

    public function onResume(): void
    {
        if ($this->awaitingExactPermission) {
            $this->awaitingExactPermission = false;
            if (! Alarm::canSchedule()) {
                $this->showErrorToast(__('app.exact_alarm_permission_denied'));

                return;
            }
            $this->scheduleAfterPermissions();

            return;
        }

        if ($this->awaitingNotificationPermission && Alarm::canPostNotifications()) {
            $this->scheduleAfterPermissions();
        }
    }

    #[On(NotificationAuthorizationChanged::class)]
    public function handleNotificationAuthorizationChanged(bool $granted, string $requestId): void
    {
        if (! $this->awaitingNotificationPermission || ! hash_equals($this->notificationPermissionRequestId, $requestId)) {
            return;
        }

        $this->awaitingNotificationPermission = false;
        $this->notificationPermissionRequestId = '';
        if (! $granted) {
            $this->showErrorToast(__('app.notification_permission_denied'));

            return;
        }

        $this->scheduleAfterPermissions();
    }

    public function render(): View
    {
        return view('native.bedtime-reminder');
    }

    private function scheduleAfterPermissions(): void
    {
        if (! $this->enabled) {
            return;
        }

        if (! Alarm::canSchedule()) {
            $this->awaitingExactPermission = true;
            Alarm::requestAuthorization();
            $this->showErrorToast(__('app.exact_alarm_permission_title'));

            return;
        }

        if (! Alarm::canPostNotifications()) {
            $this->awaitingNotificationPermission = true;
            $this->notificationPermissionRequestId = (string) Str::uuid();
            Alarm::requestNotificationAuthorization($this->notificationPermissionRequestId);

            return;
        }

        try {
            $weekdays = array_map(fn (int $day): Weekday => Weekday::from(match ($day) {
                1 => 'monday',
                2 => 'tuesday',
                3 => 'wednesday',
                4 => 'thursday',
                5 => 'friday',
                6 => 'saturday',
                default => 'sunday',
            }), $this->selectedWeekdays());
            Alarm::scheduleBedtimeReminder(new BedtimeReminderConfiguration(
                time: $this->time,
                weekdays: $weekdays,
                title: __('app.bedtime_reminder_title'),
                body: __('app.bedtime_reminder_body'),
            ));
            app(BedtimeReminderSettings::class)->save(true, $this->time, $this->selectedWeekdays());
            $this->showSuccessToast();
        } catch (AlarmException $exception) {
            report($exception);
            $this->showErrorToast(__('app.bedtime_reminder_error'));
        }
    }

    /** @return list<int> */
    private function selectedWeekdays(): array
    {
        return collect([1 => $this->monday, 2 => $this->tuesday, 3 => $this->wednesday, 4 => $this->thursday, 5 => $this->friday, 6 => $this->saturday, 7 => $this->sunday])
            ->filter()
            ->keys()
            ->map(fn (int $day): int => $day)
            ->values()
            ->all();
    }

    private function showSuccessToast(): void
    {
        Toast::success(__('app.bedtime_reminder_saved'))->position('top')->duration(3000)->show();
    }

    private function showErrorToast(string $message): void
    {
        Toast::error($message)->position('top')->duration(4000)->show();
    }
}
