<?php

namespace Momotombo\NativePHPAlarms\DTO;

use Momotombo\NativePHPAlarms\Enums\Weekday;
use Momotombo\NativePHPAlarms\Exceptions\InvalidAlarmConfiguration;

final readonly class AlarmConfiguration
{
    /** @param list<Weekday> $weekdays @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public int $hour = 0,
        public int $minute = 0,
        public array $weekdays = [],
        public ?string $label = null,
        public ?string $sound = null,
        public bool $vibration = false,
        public ?int $snoozeMinutes = null,
        public array $metadata = [],
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

    public static function make(string $id): self
    {
        return new self(id: $id);
    }

    public function at(string $time): self
    {
        if (preg_match('/^(?<hour>[01][0-9]|2[0-3]):(?<minute>[0-5][0-9])$/', $time, $matches) !== 1) {
            throw new InvalidAlarmConfiguration('Alarm time must use the HH:MM format.');
        }

        return $this->with(hour: (int) $matches['hour'], minute: (int) $matches['minute']);
    }

    /** @param list<Weekday> $weekdays */
    public function repeatOn(array $weekdays): self
    {
        return $this->with(weekdays: $weekdays);
    }

    public function label(?string $label): self
    {
        return $this->with(label: $label);
    }

    public function sound(?string $sound): self
    {
        return $this->with(sound: $sound);
    }

    public function vibration(bool $enabled = true): self
    {
        return $this->with(vibration: $enabled);
    }

    public function snooze(int $minutes): self
    {
        return $this->with(snoozeMinutes: $minutes);
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        return $this->with(metadata: $metadata);
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'hour' => $this->hour,
            'minute' => $this->minute,
            'weekdays' => array_map(fn (Weekday $weekday): string => $weekday->value, $this->weekdays),
            'label' => $this->label,
            'sound' => $this->sound,
            'vibration' => $this->vibration,
            'snooze_minutes' => $this->snoozeMinutes,
            'metadata' => $this->metadata,
        ];
    }

    /** @param array<string, mixed> $payload */
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
            sound: isset($payload['sound']) ? (string) $payload['sound'] : null,
            vibration: (bool) ($payload['vibration'] ?? false),
            snoozeMinutes: isset($payload['snooze_minutes']) ? (int) $payload['snooze_minutes'] : null,
            metadata: is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
        );
    }

    /** @param list<Weekday>|null $weekdays @param array<string, mixed>|null $metadata */
    private function with(
        ?int $hour = null,
        ?int $minute = null,
        ?array $weekdays = null,
        ?string $label = null,
        ?string $sound = null,
        ?bool $vibration = null,
        ?int $snoozeMinutes = null,
        ?array $metadata = null,
    ): self {
        return new self(
            id: $this->id,
            hour: $hour ?? $this->hour,
            minute: $minute ?? $this->minute,
            weekdays: $weekdays ?? $this->weekdays,
            label: $label ?? $this->label,
            sound: $sound ?? $this->sound,
            vibration: $vibration ?? $this->vibration,
            snoozeMinutes: $snoozeMinutes ?? $this->snoozeMinutes,
            metadata: $metadata ?? $this->metadata,
        );
    }
}
