<?php

use App\Application\PlayerProgress\DailyQuestionPicker;
use App\Application\Preferences\AppPreferences;
use App\Models\DailyQuestion;
use App\NativeComponents\DailyQuestionCard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Native\Mobile\Facades\Device;
use Native\Mobile\Testing\Native;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    app(AppPreferences::class)->setLanguage('es_NI');
    $this->travelTo(CarbonImmutable::parse('2026-10-20 14:00:00', 'UTC'));
});

it('keeps one question from the selected theme for the whole day', function () {
    app(AppPreferences::class)->setChallengeTheme('football');
    $picker = app(DailyQuestionPicker::class);

    $morning = $picker->today();
    app(AppPreferences::class)->setChallengeTheme('animals');
    $this->travel(3)->hours();
    $afternoon = $picker->today();

    expect($afternoon->is($morning))->toBeTrue()
        ->and($afternoon->challenge_theme)->toBe('football')
        ->and(array_column(trans('challenges.football.questions'), 'id'))->toContain($afternoon->question['id']);

    $this->travel(1)->day();

    expect($picker->today()->is($morning))->toBeFalse()
        ->and(DailyQuestion::query()->count())->toBe(2);
});

it('rewards a correct answer and locks the question until tomorrow', function () {
    $question = app(DailyQuestionPicker::class)->today()->question;
    $correct = array_search($question['answer'], $question['options'], true);
    $wrong = array_find_key($question['options'], fn (string $option): bool => $option !== $question['answer']);

    Native::test(DailyQuestionCard::class)
        ->assertSee('Acertala y ganá 5 XP')
        ->tap("daily-answer-{$correct}")
        ->assertSee('¡Correcto! +5 XP')
        ->assertSee('Volvé mañana por otra pregunta.')
        ->tap("daily-answer-{$wrong}")
        ->assertSee('¡Correcto! +5 XP')
        ->assertAccessible();

    expect(DailyQuestion::query()->sole())
        ->selected_option->toBe($question['answer'])
        ->correct->toBeTrue();
});

it('reveals the right answer and vibrates after a miss', function () {
    $question = app(DailyQuestionPicker::class)->today()->question;
    $wrong = array_find_key($question['options'], fn (string $option): bool => $option !== $question['answer']);
    Device::shouldReceive('vibrate')->once()->andReturnTrue();

    Native::test(DailyQuestionCard::class)
        ->tap("daily-answer-{$wrong}")
        ->assertSee('Esta vez no. La respuesta era: '.$question['answer']);

    expect(DailyQuestion::query()->sole()->correct)->toBeFalse();
});

it('opens the question of the day from settings and clears its reward badge once answered', function () {
    Native::visit('/')
        ->assertDontSee('Pregunta del día');

    Native::visit('/settings')
        ->assertSee('+5 XP')
        ->tap('open-daily-question')
        ->assertNavigatedTo('/settings/daily-question');

    $question = app(DailyQuestionPicker::class)->today()->question;
    $correct = array_search($question['answer'], $question['options'], true);
    Native::visit('/settings/daily-question')
        ->assertSee($question['question'])
        ->tap("daily-answer-{$correct}")
        ->assertSee('¡Correcto! +5 XP')
        ->assertAccessible();

    Native::visit('/settings')
        ->assertDontSee('+5 XP');
});
