<?php

namespace App\Application\Preferences;

use App\Models\AppPreference;

final class BedtimeReminderSettings
{
    private const string KEY = 'bedtime_reminder';

    /** @return array{enabled: bool, time: string, weekdays: list<int>} */
    public function get(): array
    {
        $stored = AppPreference::query()->where('key', self::KEY)->value('value');
        $settings = is_string($stored) ? json_decode($stored, true) : null;

        if (! is_array($settings) || ! is_string($settings['time'] ?? null) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $settings['time']) !== 1) {
            return ['enabled' => false, 'time' => '21:30', 'weekdays' => [1, 2, 3, 4, 5, 6, 7]];
        }

        $storedWeekdays = is_array($settings['weekdays'] ?? null) ? $settings['weekdays'] : [];
        $weekdays = array_values(array_unique(array_filter($storedWeekdays, fn (mixed $day): bool => is_int($day) && $day >= 1 && $day <= 7)));

        return [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'time' => $settings['time'],
            'weekdays' => $weekdays === [] ? [1, 2, 3, 4, 5, 6, 7] : $weekdays,
        ];
    }

    /** @param list<int> $weekdays */
    public function save(bool $enabled, string $time, array $weekdays): void
    {
        $weekdays = array_values(array_unique(array_filter($weekdays, fn (int $day): bool => $day >= 1 && $day <= 7)));
        sort($weekdays);

        AppPreference::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => json_encode(['enabled' => $enabled, 'time' => $time, 'weekdays' => $weekdays], JSON_THROW_ON_ERROR)],
        );
    }
}
