<?php

namespace App\Application\Challenges;

use App\Application\Preferences\AppPreferences;

class ChallengeCatalog
{
    public function __construct(private readonly AppPreferences $preferences) {}

    /** @param list<string> $excludedQuestionIds @return list<array{id: string, question: string, options: list<string>, answer: string}> */
    public function questions(array $excludedQuestionIds = []): array
    {
        /** @var array{questions: list<array{id: string, question: string, options: list<string>, answer: string}>} $theme */
        $theme = trans('challenges.'.$this->preferences->challengeTheme());

        $questions = collect($theme['questions'])
            ->reject(fn (array $question): bool => in_array($question['id'], $excludedQuestionIds, true))
            ->values();

        if ($questions->count() < 3) {
            $questions = collect($theme['questions']);
        }

        /** @var list<array{id: string, question: string, options: list<string>, answer: string}> $questions */
        $questions = $questions->take(3)->all();

        return $questions;
    }

    public function themeName(): string
    {
        return trans('challenges.'.$this->preferences->challengeTheme().'.name');
    }
}
