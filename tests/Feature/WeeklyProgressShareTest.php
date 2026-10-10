<?php

use App\Application\AlarmAnalytics\WeeklyProgressImage;
use App\Application\Preferences\AppPreferences;
use App\NativeComponents\Habits;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Native\Mobile\Testing\Native;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    app(AppPreferences::class)->setLanguage('es_NI');
});

it('creates a localized visual summary without alarm labels or exact times', function () {
    app()->setLocale('es_NI');
    Storage::fake('local');

    $filePath = app(WeeklyProgressImage::class)->create([
        'on_time_mornings' => 2,
        'resolved_count' => 3,
        'on_time_count' => 2,
        'late_count' => 1,
        'missed_count' => 0,
        'snooze_count' => 1,
        'hardest_weekday' => null,
        'recommendation' => 'habits_recommendation_adjust',
    ], 4, 2, 5);
    $relativePath = str_replace(Storage::disk('local')->path(''), '', $filePath);
    $files = Storage::disk('local')->files('weekly-progress-shares');
    $svg = Storage::disk('local')->get($files[0]);

    expect($filePath)->toEndWith('.svg')
        ->and($relativePath)->toContain('weekly-progress-shares')
        ->and($svg)->toContain('2 / 4')
        ->and($svg)->toContain('Mi semana con Despertá')
        ->and($svg)->toContain('Sin horarios ni etiquetas privadas')
        ->and($svg)->not->toContain('07:00')
        ->and($svg)->not->toContain('Alarma privada');
});

it('opens the native share sheet with an empty-state visual when there is no weekly data', function () {
    Storage::fake('local');

    Native::test(Habits::class)
        ->tap('share-weekly-summary')
        ->assertNativeCalled('Share.File', fn (array $parameters): bool => ($parameters['title'] ?? null) === 'Mi semana con Despertá'
            && ($parameters['message'] ?? null) === 'Un resumen privado de mis mañanas a tiempo y mi progreso.'
            && str_ends_with($parameters['filePath'] ?? '', '.svg')
            && file_exists($parameters['filePath'] ?? ''));

    $files = Storage::disk('local')->files('weekly-progress-shares');
    expect(count($files))->toBe(1)
        ->and(Storage::disk('local')->get($files[0]))->toContain('Todavía no hay resultados de alarmas para compartir.');
});
