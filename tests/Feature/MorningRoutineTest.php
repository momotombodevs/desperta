<?php

use App\Application\MorningRoutine\MorningRoutineManager;
use App\Application\Preferences\AppPreferences;
use App\Models\AlarmExecution;
use App\Models\AlarmExecutionRoutineStep;
use App\Models\MorningRoutineStep;
use App\NativeComponents\MorningRoutine;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Testing\Native;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    app(AppPreferences::class)->setLanguage('es_NI');
});

it('lets the user add, edit, reorder, and remove routine steps', function () {
    $routine = Native::test(MorningRoutine::class)
        ->set('stepLabel', 'Drink water')
        ->call('addStep')
        ->set('stepLabel', 'Stretch')
        ->call('addStep');

    $steps = MorningRoutineStep::query()->orderBy('position')->get();
    expect($steps->pluck('label')->all())->toBe(['Drink water', 'Stretch']);

    $routine->call('moveStepUp', $steps[1]->id);
    expect(MorningRoutineStep::query()->orderBy('position')->pluck('label')->all())->toBe(['Stretch', 'Drink water']);

    $routine->call('editStep', $steps[1]->id)
        ->set('editingLabel', 'Read for a minute')
        ->call('saveStep');
    expect(MorningRoutineStep::query()->findOrFail($steps[1]->id)->label)->toBe('Read for a minute');

    $routine->call('deleteStep', $steps[0]->id);
    expect(MorningRoutineStep::query()->orderBy('position')->pluck('label')->all())->toBe(['Read for a minute'])
        ->and(MorningRoutineStep::query()->value('position'))->toBe(0);
});

it('persists a routine snapshot and completion state for one alarm execution', function () {
    $manager = app(MorningRoutineManager::class);
    $first = $manager->addStep('Drink water');
    $second = $manager->addStep('Stretch');
    $execution = AlarmExecution::factory()->create(['status' => 'completed']);
    $component = Native::test(MorningRoutine::class, data: ['executionId' => $execution->id]);

    $component->assertSee('Tu alarma ya está apagada')
        ->call('startRoutine')
        ->assertSet('routineStatus', 'incomplete');

    $snapshot = AlarmExecutionRoutineStep::query()->where('alarm_execution_id', $execution->id)->orderBy('position')->get();
    expect($snapshot->pluck('label')->all())->toBe(['Drink water', 'Stretch']);

    $manager->updateStep($first, 'Take a walk');
    $manager->deleteStep($second);
    expect(AlarmExecutionRoutineStep::query()->where('alarm_execution_id', $execution->id)->pluck('label')->all())
        ->toBe(['Drink water', 'Stretch']);

    $component->call('completeRoutineStep', $snapshot[0]->id);
    expect(app(MorningRoutineManager::class)->executionStatus($execution))->toBe('incomplete');

    Native::test(MorningRoutine::class, data: ['executionId' => $execution->id])
        ->assertSee('1 de 2 pasos completados')
        ->call('completeRoutineStep', $snapshot[1]->id)
        ->assertSet('routineStatus', 'complete')
        ->assertSee('Completaste tu rutina.');

    expect(AlarmExecutionRoutineStep::query()->where('alarm_execution_id', $execution->id)->whereNotNull('completed_at')->count())
        ->toBe(2);
});

it('renders a saved execution routine after all configured steps are deleted', function () {
    $manager = app(MorningRoutineManager::class);
    $step = $manager->addStep('Drink water');
    $execution = AlarmExecution::factory()->create(['status' => 'completed']);
    Native::test(MorningRoutine::class, data: ['executionId' => $execution->id])->call('startRoutine');
    $manager->deleteStep($step);

    Native::test(MorningRoutine::class, data: ['executionId' => $execution->id])
        ->assertSee('0 de 1 pasos completados')
        ->assertSee('Drink water')
        ->call('completeRoutineStep', AlarmExecutionRoutineStep::query()->where('alarm_execution_id', $execution->id)->value('id'))
        ->assertSet('routineStatus', 'complete');
});

it('keeps routine progress separate for different alarm executions', function () {
    $manager = app(MorningRoutineManager::class);
    $manager->addStep('Drink water');
    $firstExecution = AlarmExecution::factory()->create(['status' => 'completed']);
    $secondExecution = AlarmExecution::factory()->create(['status' => 'completed']);

    $manager->beginForExecution($firstExecution);
    $manager->beginForExecution($secondExecution);
    $firstStep = $manager->executionSteps($firstExecution)->firstOrFail();
    $manager->completeStep($firstExecution, $firstStep->id);

    expect($manager->executionStatus($firstExecution))->toBe('complete')
        ->and($manager->executionStatus($secondExecution))->toBe('incomplete');
});

it('treats an empty routine as optional and rejects a routine before alarm completion', function () {
    $manager = app(MorningRoutineManager::class);
    $completedExecution = AlarmExecution::factory()->create(['status' => 'completed']);
    $manager->beginForExecution($completedExecution);

    expect($manager->executionStatus($completedExecution))->toBe('not_configured')
        ->and(fn () => $manager->beginForExecution(AlarmExecution::factory()->create(['status' => 'ringing'])))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects blank routine steps without persisting them', function () {
    $routine = Native::test(MorningRoutine::class)
        ->set('stepLabel', '   ')
        ->call('addStep');

    expect($routine->get('validationMessage'))->not->toBe('')
        ->and(MorningRoutineStep::query()->count())->toBe(0);
});

it('renders the localized optional setup state accessibly', function () {
    Native::test(MorningRoutine::class)
        ->assertSee('Todavía no agregaste pasos.')
        ->assertSee('La rutina es opcional.')
        ->assertAccessible();
});
