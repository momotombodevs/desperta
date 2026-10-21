<?php

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Challenges\ChallengeCatalog;
use App\Application\Challenges\ChallengeTheme;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use App\Models\AlarmExecution;
use App\Models\MorningRoutineStep;
use App\NativeComponents\Challenge;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Facades\Device;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;
use Random\Engine\Mt19937;
use Random\Randomizer;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

it('stops a one-time alarm after one successful challenge', function () {
    $alarm = Alarm::factory()->create([
        'weekdays' => [],
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('cancel')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);

    completeChallenge($challenge);

    $challenge
        ->assertSet('completed', true)
        ->assertSet('passed', true)
        ->assertSet('alarmStopped', true);

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'enabled' => false,
        'scheduling_status' => 'not_scheduled',
    ]);
    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'attempt_number' => 1,
        'passed' => true,
    ]);
});

it('stops the active native alarm when a notification opens the challenge without route data', function () {
    $alarm = Alarm::factory()->create([
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class);

    completeChallenge($challenge);

    $challenge
        ->assertSet('alarmId', $alarm->id)
        ->assertSet('alarmStopped', true);
});

it('starts the scheduled execution passed through the native challenge route', function () {
    $alarm = Alarm::factory()->create();
    $executionId = '018f0b8d-1d3e-7f14-8caa-111111111111';

    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, $executionId, '2026-09-03T07:00:00+00:00'));

    Native::visit("/challenge/{$alarm->id}/{$executionId}/2026-09-03T07:00:00+00:00")
        ->assertSet('alarmId', $alarm->id)
        ->assertSet('executionId', $executionId);

    $this->assertDatabaseHas('alarm_executions', [
        'id' => $executionId,
        'alarm_id' => $alarm->id,
        'status' => 'ringing',
    ]);
});

it('labels the generated challenge type separately from its trivia topic', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id])
        ->set('challengeType', 'mental_math');

    $challenge->assertSee('Reto: Cálculo mental')
        ->assertDontSee('Cálculo mental · Nicaragua');
});

it('completes a repeating alarm without cancelling its future schedule', function () {
    $alarm = Alarm::factory()->create([
        'weekdays' => [1, 3, 5],
        'scheduling_status' => 'scheduled',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    $scheduler->shouldNotReceive('cancel');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);

    completeChallenge($challenge);

    $challenge
        ->assertSet('alarmStopped', true);

    $this->assertDatabaseHas('alarms', [
        'id' => $alarm->id,
        'enabled' => true,
        'scheduling_status' => 'scheduled',
    ]);
});

it('keeps a failed challenge ringing until the user retries', function () {
    $alarm = Alarm::factory()->create(['scheduling_status' => 'scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldNotReceive('completeRinging');
    $scheduler->shouldNotReceive('cancel');
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    $answers = $challenge->get('questions');

    foreach ($answers as $index => $question) {
        $wrongAnswerIndex = array_find_key($question['options'], fn (string $option): bool => $option !== $question['answer']);
        selectChallengeAnswer($challenge, $wrongAnswerIndex);
        $challenge->call('continueChallenge');
    }

    $challenge
        ->assertSet('completed', true)
        ->assertSet('passed', false)
        ->assertSet('alarmStopped', false)
        ->assertSee('Necesitás 3 de 4. Probá con otras preguntas.');

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'attempt_number' => 1,
        'passed' => false,
    ]);
});

it('shows an encouragement hint after repeated challenge failures', function () {
    $alarm = Alarm::factory()->create(['scheduling_status' => 'scheduled']);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    foreach ($challenge->get('questions') as $question) {
        $wrongAnswerIndex = array_find_key($question['options'], fn (string $option): bool => $option !== $question['answer']);
        selectChallengeAnswer($challenge, $wrongAnswerIndex);
        $challenge->call('continueChallenge');
    }

    $challenge->call('retry');
    foreach ($challenge->get('questions') as $question) {
        $wrongAnswerIndex = array_find_key($question['options'], fn (string $option): bool => $option !== $question['answer']);
        selectChallengeAnswer($challenge, $wrongAnswerIndex);
        $challenge->call('continueChallenge');
    }

    $challenge->assertSee('Respirá y leé cada opción con calma. El próximo reto se ajustará a tus resultados recientes.');
    $this->assertDatabaseCount('alarm_challenge_attempts', 2);
});

it('allows an easy challenge to stop after two correct answers', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'easy']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    answerQuestions($challenge, [true, true, false]);

    $challenge
        ->assertSet('questionCount', 3)
        ->assertSet('requiredCorrectAnswers', 2)
        ->assertSet('passed', true)
        ->assertSet('alarmStopped', true);

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'question_count' => 3,
        'required_correct_answers' => 2,
        'correct_answers' => 2,
        'passed' => true,
    ]);
});

it('allows a hard challenge to stop after four of five correct answers', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'hard']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    answerQuestions($challenge, [true, false, true, true, true]);

    $challenge
        ->assertSet('questionCount', 5)
        ->assertSet('requiredCorrectAnswers', 4)
        ->assertSet('passed', true);

    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_id' => $alarm->id,
        'question_count' => 5,
        'required_correct_answers' => 4,
        'correct_answers' => 4,
        'passed' => true,
    ]);
});

it('shows the configured routine in a bottom sheet when the alarm is turned off', function () {
    $alarm = Alarm::factory()->create(['scheduling_status' => 'scheduled']);
    MorningRoutineStep::query()->create(['label' => 'Tomar agua', 'position' => 0]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-routine', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    completeChallenge($challenge);

    $challenge
        ->assertSet('routineSheetVisible', true)
        ->assertSee('Tomar agua')
        ->assertElement('bottom_sheet');

    $this->assertDatabaseHas('alarm_execution_routine_steps', [
        'alarm_execution_id' => 'execution-routine',
        'label' => 'Tomar agua',
    ]);

    $challenge
        ->call('dismissRoutineSheet')
        ->assertSet('routineSheetVisible', false)
        ->tap('open-morning-routine')
        ->assertSet('routineSheetVisible', true);
});

it('renders selected answer cards accessibly', function () {
    $alarm = Alarm::factory()->create();
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $challenge = Native::test(Challenge::class);
    $answerIndex = 0;

    $challenge
        ->call('showMemoryQuestion')
        ->call('selectAnswer', $answerIndex)
        ->assertSet('selectedAnswerIndex', $answerIndex)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'answer-0'
            && ($node['style']['border_width'] ?? null) === 1.0)
        ->assertAccessible();
});

it('completes and records every new challenge type with resumable progress', function (int $seed, string $type) {
    $alarm = Alarm::factory()->create(['difficulty' => 'easy', 'scheduling_status' => 'scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);
    app()->instance(ChallengeCatalog::class, new ChallengeCatalog(app(AppPreferences::class), new Randomizer(new Mt19937($seed))));

    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id])
        ->assertSet('challengeType', $type);

    if ($type === 'memory') {
        $sequence = $challenge->get('questions')[0]['memory_sequence'];
        $visibleSequence = str_replace('  ·  ', ' · ', $sequence);
        $challenge->assertSee($visibleSequence)
            ->tap('show-memory-question')
            ->assertDontSee($visibleSequence);

        Native::test(Challenge::class)->assertSet('memoryPhase', 'answer')->assertSet('questions', $challenge->get('questions'));
    }

    answerQuestions($challenge, [false, false, false]);
    Native::test(Challenge::class)->assertSet('completed', true)->assertSet('passed', false);
    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_execution_id' => 'execution-1',
        'challenge_type' => $type,
        'passed' => false,
    ]);

    $retry = Native::test(Challenge::class)->tap('retry-challenge');
    answerQuestions($retry, [true, true, false]);
    $retry->assertSet('passed', true)->assertSet('alarmStopped', true);
    $this->assertDatabaseHas('alarm_challenge_attempts', [
        'alarm_execution_id' => 'execution-1',
        'challenge_type' => $type,
        'passed' => true,
    ]);
})->with([
    [1, 'memory'],
    [3, 'sequence'],
    [5, 'mental_math'],
]);

it('shows each memory sequence before asking for its answer', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'easy', 'scheduling_status' => 'scheduled']);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    app()->instance(ChallengeCatalog::class, new ChallengeCatalog(app(AppPreferences::class), new Randomizer(new Mt19937(1))));
    $challenge = Native::test(Challenge::class, data: ['alarmId' => $alarm->id]);
    $questions = $challenge->get('questions');
    $answerIndex = array_search($questions[0]['answer'], $questions[0]['options'], true);

    selectChallengeAnswer($challenge, $answerIndex);
    $challenge->call('continueChallenge')
        ->assertSet('questionIndex', 1)
        ->assertSet('memoryPhase', 'memorize')
        ->assertSee(str_replace('  ·  ', ' · ', $questions[1]['memory_sequence']));
});

/** @param TestableComponent<Challenge> $challenge */
function completeChallenge($challenge): void
{
    foreach ($challenge->get('questions') as $question) {
        $answerIndex = array_find_key($question['options'], fn (string $option): bool => $option === $question['answer']);
        selectChallengeAnswer($challenge, $answerIndex);
        $challenge->call('continueChallenge');
    }
}

function answerQuestions($challenge, array $answers): void
{
    foreach ($challenge->get('questions') as $index => $question) {
        $answerIndex = array_find_key($question['options'], fn (string $option): bool => $answers[$index]
            ? $option === $question['answer']
            : $option !== $question['answer']);
        selectChallengeAnswer($challenge, $answerIndex);
        $challenge->call('continueChallenge');
    }
}

function selectChallengeAnswer($challenge, int $answerIndex): void
{
    if ($challenge->get('challengeType') === 'memory' && $challenge->get('memoryPhase') === 'memorize') {
        $challenge->call('showMemoryQuestion');
    }

    $challenge->call('selectAnswer', $answerIndex);
}

it('reveals the correct answer and its explanation as soon as a wrong answer is chosen', function () {
    $alarm = Alarm::factory()->create(['challenge_types' => ['mental_math']]);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    Device::shouldReceive('vibrate')->once()->andReturnTrue();
    $challenge = Native::test(Challenge::class);
    $question = $challenge->get('questions')[0];
    $wrong = array_find_key($question['options'], fn (string $option): bool => $option !== $question['answer']);
    $correct = array_search($question['answer'], $question['options'], true);

    $challenge->call('selectAnswer', $wrong)
        ->assertSet('answerRevealed', true)
        ->assertSet('correctAnswers', 0)
        ->assertSee('La respuesta correcta era: '.$question['answer'])
        ->assertSee($question['explanation'])
        ->call('selectAnswer', $correct)
        ->assertSet('selectedAnswerIndex', $wrong)
        ->assertSet('correctAnswers', 0);
});

it('counts a combo for consecutive correct answers and resets it after a mistake', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'hard', 'challenge_types' => ['mental_math']]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    $challenge = Native::test(Challenge::class);

    answerQuestions($challenge, [true, true, true, false, true]);

    $challenge->assertSet('comboCount', 1)
        ->assertSet('bestCombo', 3)
        ->assertSet('passed', true)
        ->assertSee('4 de 5 correctas')
        ->assertSee('Mejor racha: 3 seguidas');
});

it('shows the combo badge while the streak is alive', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'hard', 'challenge_types' => ['sequence']]);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $challenge = Native::test(Challenge::class);
    $questions = $challenge->get('questions');

    foreach ([0, 1] as $index) {
        $challenge->call('selectAnswer', array_search($questions[$index]['answer'], $questions[$index]['options'], true));
        if ($index === 0) {
            $challenge->call('continueChallenge');
        }
    }

    $challenge->assertSet('comboCount', 2)
        ->assertSee('Racha x2')
        ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'combo-badge');
});

it('uses the challenge types and topic configured on the alarm', function () {
    app(AppPreferences::class)->setChallengeTheme('nicaragua');
    $alarm = Alarm::factory()->create(['challenge_theme' => 'math', 'challenge_types' => ['trivia']]);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));

    $challenge = Native::test(Challenge::class)
        ->assertSet('challengeType', 'trivia')
        ->assertSet('challengeTheme', 'math')
        ->assertSee('Trivia · Matemáticas');

    expect(array_diff(array_column($challenge->get('questions'), 'id'), array_column(trans('challenges.math.questions'), 'id')))->toBeEmpty();
});

it('hides the memory sequence once the memorize time runs out', function () {
    $this->travelTo('2026-09-03 07:00:00');
    $alarm = Alarm::factory()->create(['difficulty' => 'easy', 'challenge_types' => ['memory']]);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $challenge = Native::test(Challenge::class);
    $question = $challenge->get('questions')[0];
    $visibleSequence = str_replace('  ·  ', ' · ', $question['memory_sequence']);

    $challenge->assertSet('memoryDeadline', now()->timestamp + 10)
        ->assertSee($visibleSequence)
        ->assertSee('10')
        ->call('selectAnswer', 0)
        ->assertSet('answerRevealed', false);

    $this->travel(11)->seconds();

    Native::test(Challenge::class)
        ->assertDontSee($visibleSequence)
        ->assertSee($question['question'])
        ->call('selectAnswer', 0)
        ->assertSet('answerRevealed', true);
});

it('labels a mixed trivia question with the pack it came from and its level', function () {
    $alarm = Alarm::factory()->create(['difficulty' => 'easy', 'challenge_theme' => 'mix', 'challenge_types' => ['trivia']]);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));

    $challenge = Native::test(Challenge::class);
    $question = $challenge->get('questions')[0];
    $pack = ChallengeTheme::from(strstr($question['id'], '.', true));

    $challenge->assertSee('Trivia · '.$pack->label())
        ->assertSee('Nivel Fácil')
        ->assertSet('challengeTheme', 'mix');
});

it('celebrates the XP and achievements earned when the alarm turns off', function () {
    $this->travelTo('2026-09-03 07:01:00');
    $alarm = Alarm::factory()->create(['difficulty' => 'easy', 'challenge_types' => ['mental_math'], 'scheduling_status' => 'scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class);
    completeChallenge($challenge);

    $challenge->assertSet('alarmStopped', true)
        ->assertSet('earnedXp', 20 + 10 + 15)
        ->assertSet('unlockedAchievements', ['first_wake', 'lightning'])
        ->assertSee('+45 XP')
        ->assertSee('Logro desbloqueado')
        ->assertSee('Primer despertar')
        ->assertSee('Rayo');

    $this->assertDatabaseHas('alarm_challenge_attempts', ['alarm_execution_id' => 'execution-1', 'best_combo' => 3]);
    expect(app(AppPreferences::class)->seenAchievements())->toBe(['first_wake', 'lightning']);
});

it('announces a level up when the wake-up crosses the next threshold', function () {
    $this->travelTo('2026-09-03 07:01:00');
    $alarm = Alarm::factory()->create(['difficulty' => 'easy', 'challenge_types' => ['sequence'], 'scheduling_status' => 'scheduled']);
    AlarmExecution::factory()->for($alarm)->count(3)->create([
        'scheduled_for' => '2026-09-01 07:00:00', 'started_at' => '2026-09-01 07:00:00', 'finished_at' => '2026-09-01 07:03:00',
    ]);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);

    $challenge = Native::test(Challenge::class);
    completeChallenge($challenge);

    $challenge->assertSet('reachedLevel', 2)
        ->assertSee('¡Subiste al nivel 2: Madrugador!');
});

it('scores an attempt that ended early against the questions actually answered', function () {
    $alarm = Alarm::factory()->create(['challenge_types' => ['mental_math']]);
    mock(NativeAlarmScheduler::class)->shouldReceive('activeRingingOccurrence')
        ->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $challenge = Native::test(Challenge::class);

    answerQuestions($challenge, [false, false, false, false]);

    $challenge->assertSet('completed', true)
        ->assertSet('answeredCount', 2)
        ->assertSee('0 / 2')
        ->assertSee('Ya no alcanzabas los 3 aciertos, así que terminamos antes.');
    $this->assertDatabaseHas('alarm_challenge_attempts', ['alarm_execution_id' => 'execution-1', 'question_count' => 4, 'correct_answers' => 0]);
});

it('adds the routine XP to the celebration once the morning routine is finished', function () {
    $this->travelTo('2026-09-03 07:01:00');
    MorningRoutineStep::query()->create(['label' => 'Tomar agua', 'position' => 0]);
    $alarm = Alarm::factory()->create(['difficulty' => 'easy', 'challenge_types' => ['mental_math'], 'scheduling_status' => 'scheduled']);
    $scheduler = mock(NativeAlarmScheduler::class);
    $scheduler->shouldReceive('activeRingingOccurrence')->andReturn(new ActiveAlarmOccurrence($alarm->id, 'execution-1', '2026-09-03T07:00:00+00:00'));
    $scheduler->shouldReceive('completeRinging')->once()->with($alarm->id);
    app()->instance(NativeAlarmScheduler::class, $scheduler);
    $challenge = Native::test(Challenge::class);
    completeChallenge($challenge);
    $challenge->assertSet('earnedXp', 45);

    $step = $challenge->instance()->routineSteps->sole();
    $challenge->call('completeRoutineStep', $step->id)
        ->assertSet('earnedXp', 55)
        ->assertSee('+55 XP')
        ->assertSee('+10 XP');
});
