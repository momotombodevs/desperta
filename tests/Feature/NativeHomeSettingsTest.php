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

it('opens Momotombo Devs in the in-app browser from settings', function () {
    Native::visit('/settings')
        ->tap('momotombo-devs')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $parameters): bool => $parameters === ['url' => 'https://momotombo.dev/']);
});

it('configures the public Despertá privacy policy URL by default', function () {
    expect(config('services.desperta.privacy_policy_url'))
        ->toBe('https://desperta.momotombo.dev/privacy.html');
});

it('opens the configured privacy policy in the in-app browser from settings', function () {
    config()->set('services.desperta.privacy_policy_url', 'https://example.test/privacy');

    Native::visit('/settings')
        ->tap('privacy-policy')
        ->assertNativeCalled('Browser.OpenInApp', fn (array $parameters): bool => $parameters === ['url' => 'https://example.test/privacy']);
});

it('translates the privacy policy entry when the language changes', function () {
    Native::visit('/settings')
        ->assertSee('Política de privacidad')
        ->tap('language-english')
        ->assertSee('Privacy policy');
});

it('uses the bottom action bar to navigate to the alarm editor', function () {
    Native::visit('/')
        ->assertElement('bottom_bar')
        ->tap('create-alarm')
        ->assertNavigatedTo('/alarms/new')
        ->assertTransition(Transition::SlideFromBottom);
});
