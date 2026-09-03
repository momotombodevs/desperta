<?php

use App\Models\AppPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Edge\Transition;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('opens the compact settings sheet and persists its selections', function () {
    Native::visit('/')
        ->tap('settings')
        ->assertSet('settingsOpen', true)
        ->assertSee('Apariencia')
        ->assertElement('svg', fn (array $node): bool => ($node['props']['alt'] ?? null) === 'Despertá'
            && str_ends_with($node['props']['src'] ?? '', '/public/images/brand/desperta-mark.svg'))
        ->assertElement('svg', fn (array $node): bool => ($node['props']['alt'] ?? null) === 'Bandera de Nicaragua'
            && str_ends_with($node['props']['src'] ?? '', '/public/images/flags/nicaragua.svg'))
        ->assertElement('svg', fn (array $node): bool => ($node['props']['alt'] ?? null) === 'Bandera de Estados Unidos'
            && str_ends_with($node['props']['src'] ?? '', '/public/images/flags/united-states.svg'))
        ->assertSee('Bandera de Nicaragua')
        ->assertSee('Bandera de Estados Unidos');

    Native::visit('/')
        ->tap('settings')
        ->tap('language-english')
        ->assertSet('languagePreference', 'en')
        ->set('appearanceSelection', 2)
        ->set('challengeThemeSelection', 1)
        ->assertAccessible();

    Native::visit('/')
        ->tap('settings')
        ->call('closeSettings')
        ->assertSet('settingsOpen', false);

    expect(AppPreference::query()->pluck('value', 'key')->all())
        ->toHaveKey('appearance', 'dark')
        ->toHaveKey('language', 'en')
        ->toHaveKey('challenge_theme', 'math');
});

it('does not render radio groups in settings', function () {
    Native::visit('/')
        ->tap('settings')
        ->assertMissingElement('radio_group');
});

it('uses the floating action button to navigate to the alarm editor', function () {
    Native::visit('/')
        ->tap('create-alarm')
        ->assertNavigatedTo('/alarms/new')
        ->assertTransition(Transition::SlideFromBottom);
});
