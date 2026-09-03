<?php

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
