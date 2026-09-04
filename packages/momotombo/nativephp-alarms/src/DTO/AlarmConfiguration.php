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
        public ?int $snoozeMinutes = null,
        public ?string $launchPath = null,
        public ?string $notificationTitle = null,
        public ?string $notificationBody = null,
        public ?string $occurrenceId = null,
        public ?string $scheduledFor = null,
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
     * @return array{id: string, hour: int, minute: int, weekdays: list<string>, label: ?string, vibration: bool, snooze_minutes: ?int, launch_path: ?string, notification_title: ?string, notification_body: ?string, occurrence_id: ?string, scheduled_for: ?string}
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
            'snooze_minutes' => $this->snoozeMinutes,
            'launch_path' => $this->launchPath,
            'notification_title' => $this->notificationTitle,
            'notification_body' => $this->notificationBody,
            'occurrence_id' => $this->occurrenceId,
            'scheduled_for' => $this->scheduledFor,
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
                fn (mixed $weekday): Weekday => Weekday::from((string) $weekday),
                $payload['weekdays'] ?? [],
            ),
            label: isset($payload['label']) ? (string) $payload['label'] : null,
            vibration: (bool) ($payload['vibration'] ?? false),
            snoozeMinutes: isset($payload['snooze_minutes']) ? (int) $payload['snooze_minutes'] : null,
            launchPath: isset($payload['launch_path']) ? (string) $payload['launch_path'] : null,
            notificationTitle: isset($payload['notification_title']) ? (string) $payload['notification_title'] : null,
            notificationBody: isset($payload['notification_body']) ? (string) $payload['notification_body'] : null,
            occurrenceId: isset($payload['occurrence_id']) ? (string) $payload['occurrence_id'] : null,
            scheduledFor: isset($payload['scheduled_for']) ? (string) $payload['scheduled_for'] : null,
        );
    }

    /** @param list<Weekday>|null $weekdays */
    private function with(
        ?int $hour = null,
        ?int $minute = null,
        ?array $weekdays = null,
        ?string $label = null,
        ?bool $vibration = null,
        ?int $snoozeMinutes = null,
        ?string $launchPath = null,
        ?string $notificationTitle = null,
        ?string $notificationBody = null,
        ?string $occurrenceId = null,
        ?string $scheduledFor = null,
    ): self {
        return new self(
            id: $this->id,
            hour: $hour ?? $this->hour,
            minute: $minute ?? $this->minute,
            weekdays: $weekdays ?? $this->weekdays,
            label: $label ?? $this->label,
            vibration: $vibration ?? $this->vibration,
            snoozeMinutes: $snoozeMinutes ?? $this->snoozeMinutes,
            launchPath: $launchPath ?? $this->launchPath,
            notificationTitle: $notificationTitle ?? $this->notificationTitle,
            notificationBody: $notificationBody ?? $this->notificationBody,
            occurrenceId: $occurrenceId ?? $this->occurrenceId,
            scheduledFor: $scheduledFor ?? $this->scheduledFor,
        );
    }
}
