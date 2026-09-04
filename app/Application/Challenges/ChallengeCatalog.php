<?php

namespace App\Application\Challenges;

use App\Application\Preferences\AppPreferences;
use Random\Randomizer;

class ChallengeCatalog
{
    public function __construct(
        private readonly AppPreferences $preferences,
        private readonly ?Randomizer $randomizer = null,
    ) {}

    /** @param list<string> $excludedQuestionIds @return list<array{id: string, question: string, options: list<string>, answer: string}> */
    public function questions(array $excludedQuestionIds = [], ?string $previousFingerprint = null): array
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
        $questions = $this->shuffle($questions->all());
        $questions = array_map(
            fn (array $question): array => [
                ...$question,
                'options' => $this->shuffle($question['options']),
            ],
            array_slice($questions, 0, 3),
        );

        if ($previousFingerprint !== null && $previousFingerprint !== '' && $this->fingerprint($questions) === $previousFingerprint) {
            [$questions[0], $questions[1]] = [$questions[1], $questions[0]];
        }

        return $questions;
    }

    /** @param list<array{id: string, question: string, options: list<string>, answer: string}> $questions */
    public function fingerprint(array $questions): string
    {
        return hash('sha256', json_encode(array_map(
            fn (array $question): array => [$question['id'], $question['options']],
            $questions,
        ), JSON_THROW_ON_ERROR));
    }

    public function themeName(): string
    {
        return trans('challenges.'.$this->preferences->challengeTheme().'.name');
    }

    /** @template T @param list<T> $items @return list<T> */
    private function shuffle(array $items): array
    {
        return ($this->randomizer ?? new Randomizer)->shuffleArray($items);
    }
}
