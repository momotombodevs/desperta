<?php

namespace App\Application\PlayerProgress;

use App\Application\Challenges\ChallengeCatalog;
use App\Application\Preferences\AppPreferences;
use App\Models\DailyQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Translation\Translator;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Picks one trivia question per calendar day from the selected theme and keeps it
 * stable for the whole day, even if the theme or the app's language changes.
 */
final class DailyQuestionPicker
{
    public function __construct(
        private readonly AppPreferences $preferences,
        private readonly ChallengeCatalog $catalog,
        private readonly Translator $translator,
    ) {}

    public function today(): DailyQuestion
    {
        $date = $this->todayDate();

        return DailyQuestion::query()->firstOrCreate(['date' => $date], function () use ($date): array {
            $theme = $this->preferences->challengeTheme();
            $seededCatalog = new ChallengeCatalog($this->preferences, new Randomizer(new Mt19937(crc32($date))));
            $picked = $seededCatalog->questions(1, themeName: $theme)[0];
            $original = $this->catalog->findQuestion($theme, $picked['id']) ?? $picked;

            return [
                'challenge_theme' => $theme,
                'question' => [
                    ...$seededCatalog->triviaQuestion($picked),
                    'locale' => $this->translator->getLocale(),
                    'option_order' => array_map(fn (string $option): int => (int) array_search($option, $original['options'], true), $picked['options']),
                ],
            ];
        });
    }

    /**
     * Today's question in the current language, keeping the option order it was first shown with.
     *
     * @return array{id: string, question: string, options: list<string>, answer: string, explanation: ?string, selected_index: ?int}
     */
    public function presented(DailyQuestion $dailyQuestion): array
    {
        $stored = $dailyQuestion->question;
        $selectedIndex = $dailyQuestion->selected_option === null ? null : array_search($dailyQuestion->selected_option, $stored['options'], true);
        $presented = [
            'id' => $stored['id'],
            'question' => $stored['question'],
            'options' => $stored['options'],
            'answer' => $stored['answer'],
            'explanation' => $stored['explanation'] ?? null,
            'selected_index' => $selectedIndex === false ? null : $selectedIndex,
        ];

        if (($stored['locale'] ?? $this->translator->getLocale()) === $this->translator->getLocale() || ! isset($stored['option_order'])) {
            return $presented;
        }

        $localized = $this->catalog->findQuestion($dailyQuestion->challenge_theme, $stored['id']);

        if ($localized === null || count($localized['options']) !== count($stored['option_order'])) {
            return $presented;
        }

        return [
            ...$presented,
            'question' => $localized['question'],
            'options' => array_map(fn (int $index): string => $localized['options'][$index], $stored['option_order']),
            'answer' => $localized['answer'],
            'explanation' => $localized['fact'] ?? null,
        ];
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

    public function isAnsweredToday(): bool
    {
        return DailyQuestion::query()
            ->where('date', $this->todayDate())
            ->whereNotNull('answered_at')
            ->exists();
    }

    private function todayDate(): string
    {
        return CarbonImmutable::now((string) config('app.alarm_timezone'))->toDateString();
    }
}
