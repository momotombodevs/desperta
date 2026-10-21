<?php

namespace App\Application\PlayerProgress;

use App\Application\Challenges\ChallengeCatalog;
use App\Application\Preferences\AppPreferences;
use App\Models\DailyQuestion;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Picks one trivia question per calendar day from the selected theme and keeps it
 * stable for the whole day, even if the theme or the app's state changes.
 */
final class DailyQuestionPicker
{
    public function __construct(private readonly AppPreferences $preferences) {}

    public function today(): DailyQuestion
    {
        $date = CarbonImmutable::now((string) config('app.alarm_timezone'))->toDateString();

        return DailyQuestion::query()->firstOrCreate(['date' => $date], function () use ($date): array {
            $theme = $this->preferences->challengeTheme();
            $catalog = new ChallengeCatalog($this->preferences, new Randomizer(new Mt19937(crc32($date))));

            return [
                'challenge_theme' => $theme,
                'question' => $catalog->triviaQuestion($catalog->questions(1, themeName: $theme)[0]),
            ];
        });
    }

    public function answer(int $optionIndex): DailyQuestion
    {
        $dailyQuestion = $this->today();
        $option = $dailyQuestion->question['options'][$optionIndex] ?? null;

        if ($dailyQuestion->isAnswered() || ! is_string($option)) {
            return $dailyQuestion;
        }

        $dailyQuestion->update([
            'selected_option' => $option,
            'correct' => $option === $dailyQuestion->question['answer'],
            'answered_at' => now(),
        ]);

        return $dailyQuestion;
    }
}
