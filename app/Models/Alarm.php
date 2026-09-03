<?php

namespace App\Models;

use Database\Factories\AlarmFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alarm extends Model
{
    /** @use HasFactory<AlarmFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'time',
        'label',
        'weekdays',
        'vibration',
        'snooze_enabled',
        'difficulty',
        'enabled',
        'scheduling_status',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'vibration' => 'boolean',
            'snooze_enabled' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function repeatLabel(): string
    {
        $labels = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            7 => 'Dom',
        ];

        return collect($this->weekdays)
            ->map(fn (int $weekday): string => $labels[$weekday])
            ->implode(', ');
    }

    public function repeatsWeekly(): bool
    {
        return $this->weekdays !== [];
    }

    public function challengeAttempts(): HasMany
    {
        return $this->hasMany(AlarmChallengeAttempt::class);
    }

    public function displayTime(): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->time));

        return sprintf(
            '%d:%02d %s',
            $hour % 12 ?: 12,
            $minute,
            $hour < 12 ? __('app.time_am') : __('app.time_pm'),
        );
    }
}
