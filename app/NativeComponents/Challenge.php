<?php

namespace App\NativeComponents;

use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Challenges\ChallengeCatalog;
use App\Application\Challenges\ChallengeDifficulty;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use App\Models\AlarmChallengeAttempt;
use App\Models\AlarmExecution;
use Illuminate\View\View;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;
use Native\Mobile\Edge\NativeComponent;

class Challenge extends NativeComponent
{
    public string $alarmId = '';

    public string $executionId = '';

    public bool $snoozeAvailable = false;

    public int $snoozeMinutes = 5;

    /** @var list<array{id: string, question: string, options: list<string>, answer: string}> */
    public array $questions = [];

    /** @var list<string> */
    public array $usedQuestionIds = [];

    public int $questionIndex = 0;

    public ?int $selectedAnswerIndex = null;

    public int $correctAnswers = 0;

    public int $questionCount = 3;

    public int $requiredCorrectAnswers = 3;

    public int $attemptNumber = 1;

    public bool $completed = false;

    public bool $passed = false;

    public bool $alarmStopped = false;

    public function mount(): void
    {
        app(AppPreferences::class)->applyLanguage();
        app(AlarmOccurrenceReconciler::class)->reconcile();
        $this->alarmId = (string) $this->param('alarmId', $this->data('alarmId', request()->query('alarmId', '')));
        $this->executionId = (string) $this->param('executionId', $this->data('executionId', request()->query('executionId', '')));
        $scheduledFor = (string) $this->param('scheduledFor', $this->data('scheduledFor', request()->query('scheduledFor', '')));
        $this->recoverActiveOccurrence($scheduledFor);
        $alarm = Alarm::query()->find($this->alarmId);
        $difficulty = $alarm?->challengeDifficulty() ?? ChallengeDifficulty::Normal;
        $this->questionCount = $difficulty->questionCount();
        $this->requiredCorrectAnswers = $difficulty->requiredCorrectAnswers();
        $this->materializeQuestions();
        $this->usedQuestionIds = array_column($this->questions, 'id');

        if ($this->alarmId !== '') {
            $this->attemptNumber = AlarmChallengeAttempt::query()
                ->where('alarm_id', $this->alarmId)
                ->max('attempt_number') + 1;
        }

        $this->snoozeAvailable = $this->executionId !== '' && $alarm?->snooze_enabled === true;
        $this->snoozeMinutes = $alarm?->snoozeMinutes() ?? 5;

        if ($this->executionId === '') {
            return;
        }

        if ($scheduledFor !== '') {
            app(AlarmExecutionLifecycle::class)->begin($this->alarmId, $this->executionId, $scheduledFor);
        }
    }

    public function continueChallenge(): void
    {
        if ($this->selectedAnswerIndex === null) {
            return;
        }

        if ($this->questions[$this->questionIndex]['options'][$this->selectedAnswerIndex] === $this->questions[$this->questionIndex]['answer']) {
            $this->correctAnswers++;
        }

        $this->selectedAnswerIndex = null;

        if ($this->questionIndex === count($this->questions) - 1) {
            $this->completed = true;
            $this->passed = $this->correctAnswers >= $this->requiredCorrectAnswers;
            $this->recordAttempt();

            if ($this->passed) {
                $this->turnOffAlarm();
            }

            return;
        }

        $this->questionIndex++;
    }

    public function selectAnswer(int $answerIndex): void
    {
        if (! array_key_exists($answerIndex, $this->questions[$this->questionIndex]['options'])) {
            return;
        }

        $this->selectedAnswerIndex = $answerIndex;
    }

    public function retry(): void
    {
        $this->attemptNumber++;
        $this->questionIndex = 0;
        $this->selectedAnswerIndex = null;
        $this->correctAnswers = 0;
        $this->completed = false;
        $this->passed = false;
        $this->alarmStopped = false;
        $this->materializeQuestions($this->usedQuestionIds);
        $this->usedQuestionIds = array_merge($this->usedQuestionIds, array_column($this->questions, 'id'));
    }

    public function snoozeAlarm(): void
    {
        if (! $this->snoozeAvailable || $this->alarmStopped) {
            return;
        }

        $execution = AlarmExecution::query()->find($this->executionId);
        if ($execution === null) {
            return;
        }

        $alarm = Alarm::query()->find($this->alarmId);

        app(NativeAlarmScheduler::class)->snooze($this->alarmId, $alarm?->snoozeMinutes() ?? 5);
        app(AlarmExecutionLifecycle::class)->snooze($execution);
        $this->replace('/');
    }

    public function turnOffAlarm(): void
    {
        if (! $this->completed || ! $this->passed || $this->alarmStopped) {
            return;
        }

        $scheduler = app(NativeAlarmScheduler::class);
        $alarm = Alarm::query()->find($this->alarmId);

        if ($alarm === null) {
            $scheduler->completeRinging($this->alarmId);
            $this->alarmStopped = true;

            return;
        }

        if ($alarm->repeatsWeekly()) {
            $scheduler->completeRinging($alarm->id);
            $alarm->update(['enabled' => true, 'scheduling_status' => 'scheduled']);
        } else {
            $scheduler->cancel($alarm->id);
            $alarm->update(['enabled' => false, 'scheduling_status' => 'not_scheduled']);
        }

        if ($this->executionId !== '') {
            $execution = AlarmExecution::query()->find($this->executionId);
            if ($execution !== null) {
                app(AlarmExecutionLifecycle::class)->complete($execution);
            }
        }

        $this->alarmStopped = true;
    }

    public function returnHome(): void
    {
        if (! $this->alarmStopped) {
            return;
        }

        $this->replace('/');
    }

    private function recordAttempt(): void
    {
        AlarmChallengeAttempt::query()->create([
            'alarm_id' => $this->alarmId === '' ? null : $this->alarmId,
            'alarm_execution_id' => $this->executionId === '' ? null : $this->executionId,
            'challenge_theme' => app(AppPreferences::class)->challengeTheme(),
            'attempt_number' => $this->attemptNumber,
            'correct_answers' => $this->correctAnswers,
            'question_count' => count($this->questions),
            'required_correct_answers' => $this->requiredCorrectAnswers,
            'passed' => $this->passed,
        ]);
    }

    /** @param list<string> $excludedQuestionIds */
    private function materializeQuestions(array $excludedQuestionIds = []): void
    {
        $preferences = app(AppPreferences::class);
        $catalog = app(ChallengeCatalog::class);
        $theme = $preferences->challengeTheme();
        $this->questions = $catalog->questions($this->questionCount, $excludedQuestionIds, $preferences->lastChallengeOrder($theme));
        $preferences->rememberChallengeOrder($theme, $catalog->fingerprint($this->questions));
    }

    private function recoverActiveOccurrence(string &$scheduledFor): void
    {
        if ($this->alarmId !== '') {
            return;
        }

        try {
            $occurrence = app(NativeAlarmScheduler::class)->activeRingingOccurrence();

            if ($occurrence === null) {
                return;
            }

            $this->alarmId = $occurrence->alarmId;
            $this->executionId = $occurrence->executionId;
            $scheduledFor = $occurrence->scheduledFor;
        } catch (NativeAlarmSchedulingFailed $exception) {
            report($exception);
        }
    }

    public function render(): View
    {
        return view('native.challenge');
    }
}
