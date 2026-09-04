<?php

use App\Application\Challenges\ChallengeCatalog;
use App\Application\Preferences\AppPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Random\Engine\Mt19937;
use Random\Randomizer;

uses(RefreshDatabase::class);

it('returns the selected localized challenge questions', function () {
    $preferences = app(AppPreferences::class);
    $preferences->setLanguage('en');
    $preferences->setChallengeTheme('general_knowledge');

    $catalog = new ChallengeCatalog($preferences, new Randomizer(new Mt19937(7)));
    $questions = $catalog->questions();

    expect($catalog->themeName())->toBe('General knowledge')
        ->and($questions)->toHaveCount(3)
        ->and($questions)->each->toHaveKeys(['id', 'question', 'options', 'answer']);
});

it('materializes shuffled questions and answers without losing the correct answer', function () {
    $preferences = app(AppPreferences::class);
    $catalog = new ChallengeCatalog($preferences, new Randomizer(new Mt19937(12)));

    $questions = $catalog->questions();

    expect(array_unique(array_column($questions, 'id')))->toHaveCount(3);

    foreach ($questions as $question) {
        expect($question['options'])->toContain($question['answer']);
    }
});

it('keeps localized question identifiers aligned across Spanish and English', function () {
    $spanish = trans('challenges.nicaragua.questions', [], 'es_NI');
    $english = trans('challenges.nicaragua.questions', [], 'en');

    expect(array_column($spanish, 'id'))->toBe(array_column($english, 'id'));
});
