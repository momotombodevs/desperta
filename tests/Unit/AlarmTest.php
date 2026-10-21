<?php

use App\Application\Challenges\ChallengeTheme;
use App\Application\Challenges\ChallengeType;
use App\Models\Alarm;

test('formats alarm times in 12-hour notation', function (string $time, string $displayTime) {
    app()->setLocale('es_NI');

    $alarm = Alarm::factory()->make(['time' => $time]);

    expect($alarm->displayTime())->toBe($displayTime);
})->with([
    'midnight' => ['00:05', '12:05 a. m.'],
    'morning' => ['07:15', '7:15 a. m.'],
    'noon' => ['12:00', '12:00 p. m.'],
    'afternoon' => ['16:30', '4:30 p. m.'],
]);

test('allows every playable challenge type unless the alarm narrows them', function (?array $storedTypes, array $expectedTypes) {
    $alarm = Alarm::factory()->make(['challenge_types' => $storedTypes]);

    expect(array_map(fn (ChallengeType $type): string => $type->value, $alarm->allowedChallengeTypes()))->toBe($expectedTypes);
})->with([
    'not configured' => [null, ['trivia', 'memory', 'sequence', 'mental_math']],
    'narrowed' => [['memory', 'mental_math'], ['memory', 'mental_math']],
    'only unknown types' => [['dance'], ['trivia', 'memory', 'sequence', 'mental_math']],
]);

test('follows the settings topic unless the alarm has its own', function () {
    expect(Alarm::factory()->make(['challenge_theme' => null])->challengeTheme())->toBeNull()
        ->and(Alarm::factory()->make(['challenge_theme' => 'math'])->challengeTheme())->toBe(ChallengeTheme::Math);
});
