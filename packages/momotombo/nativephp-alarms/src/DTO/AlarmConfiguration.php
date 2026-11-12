<?php

namespace Momotombo\NativePHPAlarms\DTO;

use Momotombo\NativePHPAlarms\Enums\Weekday;
use Momotombo\NativePHPAlarms\Exceptions\InvalidAlarmConfiguration;

/**
 * Immutable transport contract from application alarm state to Android.
 *
 * The configuration carries neutral presentation and occurrence data only;
 * application-specific challenge or habit rules must remain outside the plugin.
 */
final readonly class AlarmConfiguration
{
    /** @param list<Weekday> $weekdays */
    public function __construct(
        public string $id,
        public int $hour = 0,
        public int $minute = 0,
        public array $weekdays = [],
        public ?string $label = null,
        public bool $vibration = false,
        public bool $progressiveVolume = false,
        public ?int $snoozeMinutes = null,
        public ?string $launchPath = null,
        public ?string $notificationTitle = null,
        public ?string $notificationBody = null,
        public ?string $occurrenceId = null,
        public ?string $scheduledFor = null,
        public ?string $alarmDate = null,
        public bool $snoozeEnabled = true,
    ) {
        if (trim($id) === '') {
            throw new InvalidAlarmConfiguration('An alarm id is required.');
        }

        if ($hour < 0 || $hour > 23) {
            throw new InvalidAlarmConfiguration('Alarm hour must be between 0 and 23.');
        }

        if ($minute < 0 || $minute > 59) {
            throw new InvalidAlarmConfiguration('Alarm minute must be between 0 and 59.');
        }

        if ($snoozeMinutes !== null && $snoozeMinutes < 1) {
            throw new InvalidAlarmConfiguration('Snooze duration must be at least one minute.');
        }

        if ($alarmDate !== null) {
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $alarmDate);
            $dateErrors = \DateTimeImmutable::getLastErrors();

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $alarmDate) !== 1 || ! $parsedDate || $parsedDate->format('Y-m-d') !== $alarmDate || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $weekdays !== []) {
                throw new InvalidAlarmConfiguration('Alarm date must be a valid YYYY-MM-DD date without weekly repetition.');
            }
        }

        foreach ($weekdays as $weekday) {
            if (! $weekday instanceof Weekday) {
                throw new InvalidAlarmConfiguration('Weekdays must be Weekday values.');
            }
        }

        if (count($weekdays) !== count(array_unique(array_map(fn (Weekday $weekday): string => $weekday->value, $weekdays)))) {
            throw new InvalidAlarmConfiguration('Weekdays cannot contain duplicates.');
        }
    }

    /** Create a configuration with a stable application alarm ID. */
    public static function make(string $id): self
    {
        return new self(id: $id);
    }

    /**
     * Set local wall-clock time in strict 24-hour format.
     *
     * @param  string  $time  `HH:MM`
     */
    public function at(string $time): self
    {
        if (preg_match('/^(?<hour>[01][0-9]|2[0-3]):(?<minute>[0-5][0-9])$/', $time, $matches) !== 1) {
            throw new InvalidAlarmConfiguration('Alarm time must use the HH:MM format.');
        }

        return $this->with(hour: (int) $matches['hour'], minute: (int) $matches['minute']);
    }

    /**
     * Configure weekly repetition. An empty list means one-shot.
     *
     * @param  list<Weekday>  $weekdays
     */
    public function repeatOn(array $weekdays): self
    {
        return $this->with(weekdays: $weekdays);
    }

    /** Set a one-time local calendar date. Repeating weekdays must remain empty. */
    public function onDate(?string $date): self
    {
        return $this->with(alarmDate: $date);
    }

    /** Explicitly allow or prevent snoozing for this alarm. */
    public function snoozeEnabled(bool $enabled): self
    {
        return $this->with(snoozeEnabled: $enabled);
    }

    /** Set the human-readable fallback title for a ringing notification. */
    public function label(?string $label): self
    {
        return $this->with(label: $label);
    }

    /** Enable or disable vibration while the system alarm tone plays. */
    public function vibration(bool $enabled = true): self
    {
        return $this->with(vibration: $enabled);
    }

    /** Increase playback from 20% to 100% over the native default duration. */
    public function progressiveVolume(bool $enabled = true): self
    {
        return $this->with(progressiveVolume: $enabled);
    }

    /** Set the application-selected default snooze duration in minutes. */
    public function snooze(int $minutes): self
    {
        return $this->with(snoozeMinutes: $minutes);
    }

    /** Set the optional absolute NativePHP path to open when the alarm rings. */
    public function launchPath(?string $launchPath): self
    {
        if ($launchPath !== null && ! str_starts_with($launchPath, '/')) {
            throw new InvalidAlarmConfiguration('Launch paths must begin with a slash.');
        }

        return $this->with(launchPath: $launchPath);
    }

    /** Set optional notification copy for the foreground ringing notification. */
    public function notification(?string $title, ?string $body): self
    {
        return $this->with(notificationTitle: $title, notificationBody: $body);
    }

    /**
     * Attach the application-generated occurrence identity used for idempotent reconciliation.
     *
     * @param  non-empty-string  $occurrenceId
     * @param  non-empty-string  $scheduledFor  Intended scheduled time in the application's serialized format.
     */
    public function occurrence(string $occurrenceId, string $scheduledFor): self
    {
        if (trim($occurrenceId) === '' || trim($scheduledFor) === '') {
            throw new InvalidAlarmConfiguration('An occurrence id and scheduled time are required.');
        }

        return $this->with(occurrenceId: $occurrenceId, scheduledFor: $scheduledFor);
    }

    /**
     * Serialize this configuration into the stable Android bridge payload.
     *
     * @return array{id: string, hour: int, minute: int, weekdays: list<string>, label: ?string, vibration: bool, progressive_volume: bool, snooze_minutes: ?int, launch_path: ?string, notification_title: ?string, notification_body: ?string, occurrence_id: ?string, scheduled_for: ?string, alarm_date: ?string, snooze_enabled: bool}
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'hour' => $this->hour,
            'minute' => $this->minute,
            'weekdays' => array_map(fn (Weekday $weekday): string => $weekday->value, $this->weekdays),
            'label' => $this->label,
            'vibration' => $this->vibration,
            'progressive_volume' => $this->progressiveVolume,
            'snooze_minutes' => $this->snoozeMinutes,
            'launch_path' => $this->launchPath,
            'notification_title' => $this->notificationTitle,
            'notification_body' => $this->notificationBody,
            'occurrence_id' => $this->occurrenceId,
            'scheduled_for' => $this->scheduledFor,
            'alarm_date' => $this->alarmDate,
            'snooze_enabled' => $this->snoozeEnabled,
        ];
    }

    /**
     * Rehydrate a configuration received from a trusted bridge payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            id: (string) ($payload['id'] ?? ''),
            hour: (int) ($payload['hour'] ?? 0),
            minute: (int) ($payload['minute'] ?? 0),
            weekdays: array_map(
                fn (mixed $weekday): Weekday => Weekday::tryFrom((string) $weekday)
                    ?? throw new InvalidAlarmConfiguration('Weekdays must be Weekday values.'),
                is_array($payload['weekdays'] ?? null) ? array_values($payload['weekdays']) : [],
            ),
            label: isset($payload['label']) ? (string) $payload['label'] : null,
            vibration: (bool) ($payload['vibration'] ?? false),
            progressiveVolume: (bool) ($payload['progressive_volume'] ?? false),
            snoozeMinutes: isset($payload['snooze_minutes']) ? (int) $payload['snooze_minutes'] : null,
            launchPath: isset($payload['launch_path']) ? (string) $payload['launch_path'] : null,
            notificationTitle: isset($payload['notification_title']) ? (string) $payload['notification_title'] : null,
            notificationBody: isset($payload['notification_body']) ? (string) $payload['notification_body'] : null,
            occurrenceId: isset($payload['occurrence_id']) ? (string) $payload['occurrence_id'] : null,
            scheduledFor: isset($payload['scheduled_for']) ? (string) $payload['scheduled_for'] : null,
            alarmDate: isset($payload['alarm_date']) ? (string) $payload['alarm_date'] : null,
            snoozeEnabled: (bool) ($payload['snooze_enabled'] ?? true),
        );
    }

    /**
     * Return a copy with the given constructor arguments replaced.
     *
     * Unlike null-coalescing, this lets callers clear nullable values such as
     * `label(null)` or `launchPath(null)`.
     */
    private function with(mixed ...$changes): self
    {
        return new self(...array_replace([
            'id' => $this->id,
            'hour' => $this->hour,
            'minute' => $this->minute,
            'weekdays' => $this->weekdays,
            'label' => $this->label,
            'vibration' => $this->vibration,
            'progressiveVolume' => $this->progressiveVolume,
            'snoozeMinutes' => $this->snoozeMinutes,
            'launchPath' => $this->launchPath,
            'notificationTitle' => $this->notificationTitle,
            'notificationBody' => $this->notificationBody,
            'occurrenceId' => $this->occurrenceId,
            'scheduledFor' => $this->scheduledFor,
            'alarmDate' => $this->alarmDate,
            'snoozeEnabled' => $this->snoozeEnabled,
        ], $changes));
    }
}
