<?php

use App\Models\AppPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Edge\Transition;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('opens the settings page and persists its selections', function () {
    Native::visit('/')
        ->tap('settings')
        ->assertNavigatedTo('/settings');

    Native::visit('/settings')
        ->assertSee('Apariencia')
        ->assertElement('svg', fn (array $node): bool => ($node['props']['alt'] ?? null) === 'Despertá'
            && str_ends_with($node['props']['src'] ?? '', '/public/images/brand/desperta-mark.svg'))
        ->assertElement('svg', fn (array $node): bool => ($node['props']['alt'] ?? null) === 'Bandera de Nicaragua'
            && str_ends_with($node['props']['src'] ?? '', '/public/images/flags/nicaragua.svg'))
        ->assertElement('svg', fn (array $node): bool => ($node['props']['alt'] ?? null) === 'Bandera de Estados Unidos'
            && str_ends_with($node['props']['src'] ?? '', '/public/images/flags/united-states.svg'))
        ->assertSee('Bandera de Nicaragua')
        ->assertSee('Bandera de Estados Unidos');

    Native::visit('/settings')
        ->tap('language-english')
        ->assertSet('languagePreference', 'en')
        ->set('appearanceSelection', 2)
        ->select('challenge-theme-selector', 'Math')
        ->assertAccessible();

    expect(AppPreference::query()->pluck('value', 'key')->all())
        ->toHaveKey('appearance', 'dark')
        ->toHaveKey('language', 'en')
        ->toHaveKey('challenge_theme', 'math');

});

it('uses a full-width picker instead of tabs, radio groups, or chips for challenge themes', function () {
    Native::visit('/settings')
        ->assertMissingElement('radio_group')
        ->assertMissingElement('tab_row')
        ->assertMissingElement('chip')
        ->assertElement('select', fn (array $node): bool => ($node['ref'] ?? null) === 'challenge-theme-selector')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'open-history');
});

it('opens history from the settings page', function () {
    Native::visit('/settings')
        ->tap('open-history')
        ->assertNavigatedTo('/settings/history');
});

it('uses the floating action button to navigate to the alarm editor', function () {
    Native::visit('/')
        ->tap('create-alarm')
        ->assertNavigatedTo('/alarms/new')
        ->assertTransition(Transition::SlideFromBottom);
});
