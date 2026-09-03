<?php

namespace App\NativeComponents;

use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Challenges\ChallengeCatalog;
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

    /** @var list<array{id: string, question: string, options: list<string>, answer: string}> */
    public array $questions = [];

    /** @var list<string> */
    public array $usedQuestionIds = [];

    public int $questionIndex = 0;

    public string $selectedAnswer = '';

    public int $correctAnswers = 0;

    public int $attemptNumber = 1;

    public bool $completed = false;

    public bool $passed = false;

    public bool $alarmStopped = false;

    public function mount(): void
    {
        app(AppPreferences::class)->applyLanguage();
        $this->alarmId = $this->data('alarmId', request()->query('alarmId', ''));
        $this->recoverActiveAlarmId();
        $this->executionId = (string) $this->data('executionId', request()->query('executionId', ''));
        $this->questions = app(ChallengeCatalog::class)->questions();
        $this->usedQuestionIds = array_column($this->questions, 'id');

        if ($this->alarmId !== '') {
            $this->attemptNumber = AlarmChallengeAttempt::query()
                ->where('alarm_id', $this->alarmId)
                ->max('attempt_number') + 1;
        }

        $alarm = Alarm::query()->find($this->alarmId);
        $this->snoozeAvailable = $this->executionId !== '' && $alarm?->snooze_enabled === true;

        if ($this->executionId === '') {
            return;
        }

        $scheduledFor = (string) $this->data('scheduledFor', request()->query('scheduledFor', ''));
        if ($scheduledFor !== '') {
            app(AlarmExecutionLifecycle::class)->begin($this->alarmId, $this->executionId, $scheduledFor);
        }

    }

    public function continueChallenge(): void
    {
        if ($this->selectedAnswer === '') {
            return;
        }

        if ($this->selectedAnswer === $this->questions[$this->questionIndex]['answer']) {
            $this->correctAnswers++;
        }

        $this->selectedAnswer = '';

        if ($this->questionIndex === count($this->questions) - 1) {
            $this->completed = true;
            $this->passed = $this->correctAnswers === count($this->questions);
            $this->recordAttempt();

            if ($this->passed) {
                $this->turnOffAlarm();
            }

            return;
        }

        $this->questionIndex++;
    }

    public function selectAnswer(string $answer): void
    {
        if (! in_array($answer, $this->questions[$this->questionIndex]['options'], true)) {
            return;
        }

        $this->selectedAnswer = $answer;
    }

    public function retry(): void
    {
        $this->attemptNumber++;
        $this->questionIndex = 0;
        $this->selectedAnswer = '';
        $this->correctAnswers = 0;
        $this->completed = false;
        $this->passed = false;
        $this->alarmStopped = false;
        $this->questions = app(ChallengeCatalog::class)->questions($this->usedQuestionIds);
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

        app(NativeAlarmScheduler::class)->snooze($this->alarmId, 5);
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
            'challenge_theme' => app(AppPreferences::class)->challengeTheme(),
            'attempt_number' => $this->attemptNumber,
            'correct_answers' => $this->correctAnswers,
            'question_count' => count($this->questions),
            'required_correct_answers' => count($this->questions),
            'passed' => $this->passed,
        ]);
    }

    private function recoverActiveAlarmId(): void
    {
        if ($this->alarmId !== '') {
            return;
        }

        try {
            $this->alarmId = app(NativeAlarmScheduler::class)->activeRingingAlarmId() ?? '';
        } catch (NativeAlarmSchedulingFailed $exception) {
            report($exception);
        }
    }

    public function render(): View
    {
        return view('native.challenge');
    }
}
