<?php

use App\Application\Challenges\ChallengeCatalog;
use App\Application\Challenges\ChallengeDifficulty;
use App\Application\Challenges\ChallengeType;
use App\Application\Preferences\AppPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Random\Engine\Mt19937;
use Random\Randomizer;

uses(RefreshDatabase::class);

it('returns the selected localized challenge questions', function () {
    $preferences = app(AppPreferences::class);
    $preferences->setLanguage('en');
    $preferences->setChallengeTheme('general_knowledge');

    $catalog = new ChallengeCatalog($preferences, new Randomizer(new Mt19937(7)));
    $questions = $catalog->questions(3);

    expect($catalog->themeName())->toBe('General knowledge')
        ->and($questions)->toHaveCount(3)
        ->and($questions)->each->toHaveKeys(['id', 'question', 'options', 'answer']);
});

it('materializes shuffled questions and answers without losing the correct answer', function () {
    $preferences = app(AppPreferences::class);
    $catalog = new ChallengeCatalog($preferences, new Randomizer(new Mt19937(12)));

    $questions = $catalog->questions(3);

    expect(array_unique(array_column($questions, 'id')))->toHaveCount(3);

    foreach ($questions as $question) {
        expect($question['options'])->toContain($question['answer']);
    }
});

it('returns five questions for a hard challenge', function () {
    $catalog = new ChallengeCatalog(app(AppPreferences::class), new Randomizer(new Mt19937(19)));

    expect($catalog->questions(5))->toHaveCount(5);
});

it('keeps localized question identifiers aligned across Spanish and English', function () {
    $spanish = trans('challenges.nicaragua.questions', [], 'es_NI');
    $english = trans('challenges.nicaragua.questions', [], 'en');

    expect(array_column($spanish, 'id'))->toBe(array_column($english, 'id'));
});

it('builds valid memory, sequence, and mental math questions at each difficulty', function () {
    $catalog = new ChallengeCatalog(app(AppPreferences::class), new Randomizer(new Mt19937(29)));

    foreach ([ChallengeType::Memory, ChallengeType::Sequence, ChallengeType::MentalMath] as $type) {
        foreach (ChallengeDifficulty::cases() as $difficulty) {
            $questions = $catalog->questionsForType($type, 3, $difficulty);

            expect($questions)->toHaveCount(3);
            foreach ($questions as $question) {
                expect($question['options'])->toHaveCount(4)
                    ->and($question['options'])->toContain($question['answer'])
                    ->and($question['instruction'])->not->toBeEmpty();
            }
        }
    }
});

it('falls back to the local general knowledge package when selected content is invalid', function () {
    Lang::addLines([
        'challenges.broken' => ['questions' => [['id' => 'bad', 'question' => '', 'options' => ['one'], 'answer' => 'missing']]],
    ], 'es_NI', 'challenges');
    $catalog = new ChallengeCatalog(app(AppPreferences::class), new Randomizer(new Mt19937(31)));

    $questions = $catalog->questions(3, themeName: 'broken');

    expect($questions)->toHaveCount(3)
        ->and(array_diff(array_column($questions, 'id'), array_column(trans('challenges.general_knowledge.questions'), 'id')))->toBeEmpty();
});
