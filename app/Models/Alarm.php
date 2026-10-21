<?php

namespace App\Models;

use App\Application\Challenges\ChallengeDifficulty;
use App\Application\Challenges\ChallengeTheme;
use App\Application\Challenges\ChallengeType;
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
        'alarm_date',
        'vibration',
        'snooze_enabled',
        'snooze_minutes',
        'difficulty',
        'challenge_theme',
        'challenge_types',
        'enabled',
        'scheduling_status',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'alarm_date' => 'date:Y-m-d',
            'vibration' => 'boolean',
            'snooze_enabled' => 'boolean',
            'snooze_minutes' => 'integer',
            'challenge_types' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function repeatLabel(): string
    {
        return collect($this->weekdays)
            ->map(fn (int $weekday): string => __('app.weekday_abbreviations.'.$weekday))
            ->implode(', ');
    }

    public function scheduleSummary(): string
    {
        return collect([
            filled($this->label) ? $this->label : null,
            $this->alarm_date !== null ? $this->alarm_date->translatedFormat('j M Y') : ($this->repeatLabel() ?: __('app.once')),
        ])->filter()->implode(' · ');
    }

    public function repeatsWeekly(): bool
    {
        return $this->weekdays !== [];
    }

    public function challengeDifficulty(): ChallengeDifficulty
    {
        return ChallengeDifficulty::fromStored($this->difficulty);
    }

    /** Returns null when the alarm follows the challenge theme chosen in settings. */
    public function challengeTheme(): ?ChallengeTheme
    {
        return ChallengeTheme::tryFrom((string) $this->challenge_theme);
    }

    /** @return list<ChallengeType> */
    public function allowedChallengeTypes(): array
    {
        $types = array_values(array_filter(
            array_map(fn (mixed $type): ?ChallengeType => is_string($type) ? ChallengeType::tryFrom($type) : null, $this->challenge_types ?? []),
            fn (?ChallengeType $type): bool => $type !== null && in_array($type, ChallengeType::playable(), true),
        ));

        return $types === [] ? ChallengeType::playable() : $types;
    }

    public function snoozeMinutes(): int
    {
        return in_array($this->snooze_minutes, [5, 10, 15], true) ? $this->snooze_minutes : 5;
    }

    public function challengeAttempts(): HasMany
    {
        return $this->hasMany(AlarmChallengeAttempt::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AlarmExecution::class);
    }

    public function displayTime(): string
    {
        return self::formatClock($this->time);
    }

    /** Formats a 24-hour `H:i` time as a localized 12-hour clock, e.g. `6:30 a. m.`. */
    public static function formatClock(string $time): string
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return sprintf(
            '%d:%02d %s',
            $hour % 12 ?: 12,
            $minute,
            $hour < 12 ? __('app.time_am') : __('app.time_pm'),
        );
    }
}
