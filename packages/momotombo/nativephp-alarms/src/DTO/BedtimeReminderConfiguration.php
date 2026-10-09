<?php

namespace Momotombo\NativePHPAlarms\DTO;

use Momotombo\NativePHPAlarms\Enums\Weekday;
use Momotombo\NativePHPAlarms\Exceptions\InvalidAlarmConfiguration;

final readonly class BedtimeReminderConfiguration
{
    /** @param list<Weekday> $weekdays */
    public function __construct(
        public string $time,
        public array $weekdays,
        public string $title,
        public string $body,
    ) {
        if (preg_match('/^(?<hour>[01][0-9]|2[0-3]):(?<minute>[0-5][0-9])$/', $time) !== 1) {
            throw new InvalidAlarmConfiguration('Reminder time must use the HH:MM format.');
        }

        if ($weekdays === [] || count($weekdays) !== count(array_unique(array_map(fn (Weekday $day): string => $day->value, $weekdays)))) {
            throw new InvalidAlarmConfiguration('A reminder requires unique weekdays.');
        }
    }

    /** @return array{hour: int, minute: int, weekdays: list<string>, title: string, body: string} */
    public function toPayload(): array
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->time));

        return [
            'hour' => $hour,
            'minute' => $minute,
            'weekdays' => array_map(fn (Weekday $day): string => $day->value, $this->weekdays),
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
