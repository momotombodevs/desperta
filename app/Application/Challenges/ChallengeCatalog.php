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
    public function questions(int $count, array $excludedQuestionIds = [], ?string $previousFingerprint = null, ?string $themeName = null): array
    {
        $questions = collect($this->localizedQuestions($themeName ?? $this->preferences->challengeTheme()))
            ->reject(fn (array $question): bool => in_array($question['id'], $excludedQuestionIds, true))
            ->values();

        if ($questions->count() < $count) {
            $questions = collect($this->localizedQuestions($themeName ?? $this->preferences->challengeTheme()));
        }

        /** @var list<array{id: string, question: string, options: list<string>, answer: string}> $questions */
        $questions = $this->shuffle($questions->all());
        $questions = array_map(
            fn (array $question): array => [
                ...$question,
                'options' => $this->shuffle($question['options']),
            ],
            array_slice($questions, 0, $count),
        );

        if ($previousFingerprint !== null && $previousFingerprint !== '' && $this->fingerprint($questions) === $previousFingerprint) {
            [$questions[0], $questions[1]] = [$questions[1], $questions[0]];
        }

        return $questions;
    }

    public function chooseType(): ChallengeType
    {
        $types = ChallengeType::playable();

        return $types[$this->randomizer()->getInt(0, count($types) - 1)];
    }

    /**
     * @param  list<string>  $excludedQuestionIds
     * @return list<array{id: string, question: string, options: list<string>, answer: string, instruction: string, memory_sequence: ?string}>
     */
    public function questionsForType(ChallengeType $type, int $count, ChallengeDifficulty $difficulty, array $excludedQuestionIds = []): array
    {
        if ($type === ChallengeType::Trivia) {
            return array_map(fn (array $question): array => [
                ...$question,
                'instruction' => trans('challenges.types.trivia.instruction'),
                'memory_sequence' => null,
            ], $this->questions($count, $excludedQuestionIds));
        }

        $questions = [];

        for ($index = 0; $index < $count; $index++) {
            $questions[] = match ($type) {
                ChallengeType::Memory => $this->memoryQuestion($index, $difficulty),
                ChallengeType::Sequence => $this->sequenceQuestion($index, $difficulty),
                ChallengeType::MentalMath => $this->mentalMathQuestion($index, $difficulty),
                ChallengeType::Trivia => throw new \LogicException('Trivia questions are localized separately.'),
            };
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
        return $this->randomizer()->shuffleArray($items);
    }

    /** @return list<array{id: string, question: string, options: list<string>, answer: string}> */
    private function localizedQuestions(string $themeName): array
    {
        $theme = trans('challenges.'.$themeName);
        $questions = is_array($theme) ? ($theme['questions'] ?? null) : null;

        if (! is_array($questions) || ! $this->hasValidQuestions($questions)) {
            $fallback = trans('challenges.general_knowledge.questions');
            $questions = is_array($fallback) && $this->hasValidQuestions($fallback) ? $fallback : [];
        }

        return array_values(array_filter($questions, fn (mixed $question): bool => $this->isValidQuestion($question)));
    }

    /** @param array<mixed> $questions */
    private function hasValidQuestions(array $questions): bool
    {
        return count(array_filter($questions, fn (mixed $question): bool => $this->isValidQuestion($question))) >= 5;
    }

    private function isValidQuestion(mixed $question): bool
    {
        return is_array($question)
            && is_string($question['id'] ?? null)
            && trim($question['id']) !== ''
            && is_string($question['question'] ?? null)
            && trim($question['question']) !== ''
            && is_array($question['options'] ?? null)
            && count($question['options']) >= 2
            && count(array_filter($question['options'], 'is_string')) === count($question['options'])
            && is_string($question['answer'] ?? null)
            && in_array($question['answer'], $question['options'], true);
    }

    /** @return array{id: string, question: string, options: list<string>, answer: string, instruction: string, memory_sequence: ?string} */
    private function memoryQuestion(int $index, ChallengeDifficulty $difficulty): array
    {
        $length = match ($difficulty) {
            ChallengeDifficulty::Easy => 4,
            ChallengeDifficulty::Normal => 5,
            ChallengeDifficulty::Hard => 7,
        };
        $sequence = array_map(fn (): string => (string) $this->randomizer()->getInt(0, 9), range(1, $length));
        $targetIndex = $this->randomizer()->getInt(0, $length - 1);
        $answer = $sequence[$targetIndex];

        return [
            'id' => "memory-{$index}-".implode('', $sequence),
            'question' => trans('challenges.types.memory.question', ['position' => $targetIndex + 1]),
            'options' => $this->optionsAround($answer, 0, 9),
            'answer' => $answer,
            'instruction' => trans('challenges.types.memory.instruction'),
            'memory_sequence' => implode('  ·  ', $sequence),
        ];
    }

    /** @return array{id: string, question: string, options: list<string>, answer: string, instruction: string, memory_sequence: ?string} */
    private function sequenceQuestion(int $index, ChallengeDifficulty $difficulty): array
    {
        $start = $this->randomizer()->getInt(1, 20);
        $stepRange = match ($difficulty) {
            ChallengeDifficulty::Easy => [2, 5],
            ChallengeDifficulty::Normal => [4, 9],
            ChallengeDifficulty::Hard => [7, 14],
        };
        $step = $this->randomizer()->getInt($stepRange[0], $stepRange[1]);
        $values = array_map(fn (int $position): int => $start + ($step * $position), range(0, 3));
        $answer = (string) ($start + ($step * 4));

        return [
            'id' => "sequence-{$index}-{$start}-{$step}",
            'question' => implode(', ', array_slice($values, 0, 4)).', … ¿qué sigue?',
            'options' => $this->optionsAround((int) $answer, max(0, (int) $answer - ($step * 3)), (int) $answer + ($step * 3)),
            'answer' => $answer,
            'instruction' => trans('challenges.types.sequence.instruction'),
            'memory_sequence' => null,
        ];
    }

    /** @return array{id: string, question: string, options: list<string>, answer: string, instruction: string, memory_sequence: ?string} */
    private function mentalMathQuestion(int $index, ChallengeDifficulty $difficulty): array
    {
        $maxOperand = match ($difficulty) {
            ChallengeDifficulty::Easy => 12,
            ChallengeDifficulty::Normal => 30,
            ChallengeDifficulty::Hard => 60,
        };
        $left = $this->randomizer()->getInt(2, $maxOperand);
        $right = $this->randomizer()->getInt(2, $maxOperand);
        $operation = $this->randomizer()->getInt(0, 2);

        if ($operation === 1 && $left < $right) {
            [$left, $right] = [$right, $left];
        }

        [$symbol, $answer] = match ($operation) {
            0 => ['+', $left + $right],
            1 => ['−', $left - $right],
            default => ['×', $left * $right],
        };

        return [
            'id' => "mental-math-{$index}-{$left}-{$right}-{$operation}",
            'question' => "{$left} {$symbol} {$right} = ?",
            'options' => $this->optionsAround($answer, max(0, $answer - 10), $answer + 10),
            'answer' => (string) $answer,
            'instruction' => trans('challenges.types.mental_math.instruction'),
            'memory_sequence' => null,
        ];
    }

    /** @return list<string> */
    private function optionsAround(int|string $answer, int $min, int $max): array
    {
        $answer = (string) $answer;
        $options = [$answer];

        while (count($options) < 4) {
            $candidate = (string) $this->randomizer()->getInt($min, max($min, $max));

            if (! in_array($candidate, $options, true)) {
                $options[] = $candidate;
            }
        }

        return $this->shuffle($options);
    }

    private function randomizer(): Randomizer
    {
        return $this->randomizer ?? new Randomizer;
    }
}
