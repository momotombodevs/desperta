<?php

use App\Application\Challenges\ChallengeCatalog;
use App\Application\Preferences\AppPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the selected localized challenge questions', function () {
    $preferences = app(AppPreferences::class);
    $preferences->setLanguage('en');
    $preferences->setChallengeTheme('general_knowledge');

    $catalog = app(ChallengeCatalog::class);

    expect($catalog->themeName())->toBe('General knowledge')
        ->and($catalog->questions())->toHaveCount(3)
        ->and($catalog->questions()[0]['answer'])->toBe('Mercury');
});
