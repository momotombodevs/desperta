<?php

use App\Application\PlayerProgress\Achievement;
use App\Application\PlayerProgress\PlayerProgress;
use App\Application\Preferences\AppPreferences;
use App\Models\AlarmChallengeAttempt;
use App\Models\AlarmExecution;
use App\Models\AlarmExecutionRoutineStep;
use App\Models\DailyQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    app(AppPreferences::class)->setLanguage('es_NI');
    $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00', 'UTC'));
});

function completedWakeUp(int $minutesLate = 2, int $snoozes = 0, int $secondsToSolve = 120): AlarmExecution
{
    $scheduledFor = CarbonImmutable::parse('2026-10-19 07:00:00', 'America/Managua')->utc();

    return AlarmExecution::factory()->create([
        'status' => 'completed',
        'scheduled_for' => $scheduledFor,
        'started_at' => $scheduledFor->addMinutes($minutesLate)->subSeconds($secondsToSolve),
        'finished_at' => $scheduledFor->addMinutes($minutesLate),
        'snooze_count' => $snoozes,
    ]);
}

it('awards XP for each wake-up according to how it went', function () {
    $perfectMorning = completedWakeUp();
    AlarmChallengeAttempt::factory()->for($perfectMorning->alarm)->create([
        'alarm_execution_id' => $perfectMorning->id, 'correct_answers' => 3, 'question_count' => 3, 'passed' => true,
    ]);
    AlarmExecutionRoutineStep::query()->create([
        'alarm_execution_id' => $perfectMorning->id, 'label' => 'Tomar agua', 'position' => 0, 'completed_at' => now(),
    ]);
    $lateMorning = completedWakeUp(minutesLate: 25, snoozes: 2);
    $missedMorning = AlarmExecution::factory()->create(['status' => 'missed', 'finished_at' => null]);
    $progress = app(PlayerProgress::class);

    expect($progress->xpForExecution($perfectMorning))->toBe(20 + 10 + 15 + 10)
        ->and($progress->xpForExecution($lateMorning))->toBe(5)
        ->and($progress->xpForExecution($missedMorning))->toBe(0)
        ->and($progress->totalXp())->toBe(60);
});

it('does not reward a routine that is only partly done', function () {
    $morning = completedWakeUp();
    AlarmExecutionRoutineStep::query()->create(['alarm_execution_id' => $morning->id, 'label' => 'Agua', 'position' => 0, 'completed_at' => now()]);
    AlarmExecutionRoutineStep::query()->create(['alarm_execution_id' => $morning->id, 'label' => 'Estirar', 'position' => 1]);

    expect(app(PlayerProgress::class)->xpForExecution($morning))->toBe(30);
});

it('adds XP for every question of the day answered correctly', function () {
    DailyQuestion::factory()->answered()->create(['date' => '2026-10-18']);
    DailyQuestion::factory()->answered()->create(['date' => '2026-10-19']);
    DailyQuestion::factory()->answered(correct: false)->create(['date' => '2026-10-20']);

    expect(app(PlayerProgress::class)->totalXp())->toBe(10);
});

it('turns XP into levels with progress towards the next one', function (int $xp, int $level) {
    expect(app(PlayerProgress::class)->levelFor($xp))->toBe($level);
})->with([
    'brand new' => [0, 1],
    'almost level 2' => [99, 1],
    'level 2' => [100, 2],
    'level 4' => [500, 4],
    'top level' => [2000, 7],
    'beyond the top' => [9000, 7],
]);

it('summarizes the current level title and the XP left', function () {
    foreach (range(1, 4) as $day) {
        completedWakeUp();
    }

    expect(app(PlayerProgress::class)->summary())->toBe([
        'xp' => 120,
        'level' => 2,
        'title' => 'Madrugador',
        'level_floor' => 100,
        'next_level_xp' => 250,
        'progress' => 20 / 150,
    ]);
});

it('announces each unlocked achievement only once', function () {
    $morning = completedWakeUp(secondsToSolve: 45);
    AlarmChallengeAttempt::factory()->for($morning->alarm)->create(['alarm_execution_id' => $morning->id, 'best_combo' => 5]);
    $progress = app(PlayerProgress::class);

    expect($progress->unseenAchievements())->toBe([Achievement::FirstWake, Achievement::Lightning, Achievement::Combo5]);

    $progress->markSeen($progress->unseenAchievements());

    expect($progress->unseenAchievements())->toBe([]);
});

it('tracks partial progress for locked achievements', function () {
    foreach (['nicaragua', 'football', 'football', 'animals'] as $theme) {
        AlarmChallengeAttempt::factory()->create(['challenge_theme' => $theme, 'challenge_type' => 'trivia', 'passed' => true]);
    }
    AlarmChallengeAttempt::factory()->create(['challenge_theme' => 'history', 'challenge_type' => 'trivia', 'passed' => false]);

    $explorer = collect(app(PlayerProgress::class)->achievements())->firstWhere('achievement', Achievement::Explorer5);

    expect($explorer)->toMatchArray(['progress' => 3, 'goal' => 5, 'unlocked' => false]);
});

it('shows the level, XP and achievements on the progress screen', function () {
    completedWakeUp(secondsToSolve: 30);

    Native::visit('/settings/progress')
        ->assertSee('Nivel 1')
        ->assertSee('Dormilón')
        ->assertSee('Te faltan 70 XP para el nivel 2')
        ->assertSee('2 de 11 desbloqueados')
        ->assertSee('Primer despertar')
        ->assertSee('0 / 7')
        ->assertSee('Acertar la pregunta del día: +5 XP')
        ->assertAccessible();
});

it('opens the progress screen from home and settings', function () {
    Native::visit('/')
        ->assertSee('Nivel 1 · Dormilón')
        ->tap('open-progress')
        ->assertNavigatedTo('/settings/progress');

    Native::visit('/settings')
        ->tap('open-progress')
        ->assertNavigatedTo('/settings/progress');
});

it('adds the attempt combo column without touching existing attempts', function () {
    $attempt = AlarmChallengeAttempt::factory()->create();
    $migration = require database_path('migrations/2026_10_21_015254_add_best_combo_to_alarm_challenge_attempts_table.php');
    $migration->down();

    $migration->up();

    expect($attempt->fresh())->best_combo->toBe(0)->passed->toBeTrue();
});
