<?php

use App\Application\Preferences\AppPreferences;
use App\Models\AppPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists the supported global preferences and applies the selected locale', function () {
    $preferences = app(AppPreferences::class);

    $preferences->setAppearance('dark');
    $preferences->setLanguage('en');
    $preferences->setChallengeTheme('math');

    expect($preferences->appearance())->toBe('dark')
        ->and($preferences->language())->toBe('en')
        ->and($preferences->challengeTheme())->toBe('math')
        ->and(app()->getLocale())->toBe('en')
        ->and(AppPreference::query()->count())->toBe(3);
});
