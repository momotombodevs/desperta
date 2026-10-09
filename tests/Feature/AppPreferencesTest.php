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
    $preferences->setWeeklyGoal(6);

    expect($preferences->appearance())->toBe('dark')
        ->and($preferences->language())->toBe('en')
        ->and($preferences->challengeTheme())->toBe('math')
        ->and($preferences->weeklyGoal())->toBe(6)
        ->and(app()->getLocale())->toBe('en')
        ->and(AppPreference::query()->count())->toBe(4);
});

it('rejects weekly goals outside one to seven mornings and uses a safe default for invalid stored values', function () {
    $preferences = app(AppPreferences::class);

    expect(fn () => $preferences->setWeeklyGoal(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $preferences->setWeeklyGoal(8))->toThrow(InvalidArgumentException::class);

    AppPreference::query()->create(['key' => 'weekly_goal', 'value' => '100']);

    expect($preferences->weeklyGoal())->toBe(4);
});
