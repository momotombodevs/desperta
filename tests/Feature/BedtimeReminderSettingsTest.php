<?php

use App\Application\Preferences\BedtimeReminderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('uses a disabled local reminder with all weekdays by default', function () {
    expect(app(BedtimeReminderSettings::class)->get())->toBe([
        'enabled' => false,
        'time' => '21:30',
        'weekdays' => [1, 2, 3, 4, 5, 6, 7],
    ]);
});

it('persists only valid reminder weekdays and time', function () {
    app(BedtimeReminderSettings::class)->save(true, '22:15', [5, 1, 5, 9]);

    expect(app(BedtimeReminderSettings::class)->get())->toBe([
        'enabled' => true,
        'time' => '22:15',
        'weekdays' => [1, 5],
    ]);
    $this->assertDatabaseHas('app_preferences', ['key' => 'bedtime_reminder']);
});

it('renders the reminder as preparation copy instead of sleep tracking', function () {
    Native::visit('/settings/bedtime-reminder')
        ->assertSee('No mide ni registra tu sueño.')
        ->toggle('enabled', true)
        ->assertElement('date_picker', fn (array $node): bool => ($node['props']['mode'] ?? null) === 'time');
});
