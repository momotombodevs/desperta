<?php

use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;

uses(LazilyRefreshDatabase::class);

it('keeps permission feedback out of the English editor while preserving accessible weekday labels', function () {
    app(AppPreferences::class)->setLanguage('en');

    Native::visit('/alarms/new')
        ->assertSee('Set your alarm and choose your challenge.')
        ->assertSee('Monday')
        ->assertDontSee('Allow alarms and reminders')
        ->assertDontSee('Allow full-screen alarms')
        ->assertDontSee('Allow notifications')
        ->assertDontSee('Could not schedule the alarm');
});

it('adapts a historical Spanish difficulty to the active English locale', function () {
    app(AppPreferences::class)->setLanguage('en');
    $alarm = Alarm::factory()->create([
        'difficulty' => 'Fácil',
        'enabled' => false,
        'scheduling_status' => 'not_scheduled',
    ]);

    Native::visit("/alarms/{$alarm->id}/edit")
        ->assertSet('difficultyDisplay', 'Easy')
        ->set('difficultyDisplay', 'Hard')
        ->tap('save-alarm')
        ->assertReplacedWith('/');

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'difficulty' => 'Hard',
    ]);
});

it('uses M for both Tuesday and Wednesday in the Nicaraguan editor', function () {
    Native::visit('/alarms/new')
        ->assertElement('chip', fn (array $node): bool => ($node['props']['label'] ?? null) === 'M');
});
