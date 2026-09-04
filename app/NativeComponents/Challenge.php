<?php

namespace App\NativeComponents;

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Challenges\ChallengeCatalog;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use App\Models\AlarmChallengeAttempt;
use App\Models\AlarmExecution;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Momotombo\NativePHPAlarms\Events\AppResumed;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;

class Challenge extends NativeComponent
{
    use ResumesActiveAlarm;

    public string $alarmId = '';

    public string $executionId = '';

    public string $challengeTheme = '';

    public string $difficulty = '';

    public bool $unavailable = false;

    /** @var list<string> */
    private const array PROGRESS_PROPERTIES = [
        'questions', 'usedQuestionIds', 'questionIndex', 'selectedAnswerIndex',
        'correctAnswers', 'questionCount', 'requiredCorrectAnswers', 'attemptNumber',
        'completed', 'passed', 'challengeTheme', 'difficulty',
    ];

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
        $occurrence = $this->currentOccurrence();

        if ($occurrence === null) {
            $this->leaveUnavailableChallenge();

            return;
        }
        $this->alarmId = $occurrence->alarmId;
        $this->executionId = $occurrence->executionId;
        $execution = app(AlarmExecutionLifecycle::class)->begin($this->alarmId, $this->executionId, $occurrence->scheduledFor);

        if ($execution === null) {
            $this->leaveUnavailableChallenge();

            return;
        }

        $alarm = $execution->alarm;
        $this->snoozeAvailable = $alarm->snooze_enabled;
        $this->snoozeMinutes = $alarm->snoozeMinutes();

        if ($execution->challenge_progress !== null) {
            $this->restoreProgress($execution->challenge_progress);

            return;
        }

        $difficulty = $alarm->challengeDifficulty();
        $this->difficulty = $difficulty->value;
        $this->challengeTheme = app(AppPreferences::class)->challengeTheme();
        $this->questionCount = $difficulty->questionCount();
        $this->requiredCorrectAnswers = $difficulty->requiredCorrectAnswers();
        $this->materializeQuestions();
        $this->usedQuestionIds = array_column($this->questions, 'id');

        $this->attemptNumber = AlarmChallengeAttempt::query()
            ->where('alarm_id', $this->alarmId)
            ->max('attempt_number') + 1;

        $this->saveProgress();
    }

    protected function isShowingActiveAlarm(ActiveAlarmOccurrence $occurrence): bool
    {
        return ! $this->unavailable && ! $this->alarmStopped
            && $this->alarmId === $occurrence->alarmId
            && $this->executionId === $occurrence->executionId;
    }

    #[On(AppResumed::class)]
    public function handleAppResumed(): void
    {
        if (! $this->resumeActiveAlarm()) {
            $this->onResume();
        }
    }

    public function onResume(): void
    {
        if ($this->alarmStopped) {
            return;
        }

        app(AlarmOccurrenceReconciler::class)->reconcile();
        if ($this->currentOccurrence() === null) {
            $this->leaveUnavailableChallenge();

            return;
        }

        $this->refreshProgress();
    }

    public function continueChallenge(): void
    {
        DB::transaction(function (): void {
            if (! $this->refreshProgress() || $this->completed || $this->selectedAnswerIndex === null) {
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
            } else {
                $this->questionIndex++;
            }

            $this->saveProgress();
        });

        if ($this->completed && $this->passed && ! $this->unavailable) {
            $this->turnOffAlarm();
        }
    }

    public function selectAnswer(int $answerIndex): void
    {
        if (! $this->refreshProgress() || $this->completed || ! array_key_exists($answerIndex, $this->questions[$this->questionIndex]['options'])) {
            return;
        }

        $this->selectedAnswerIndex = $answerIndex;
        $this->saveProgress();
    }

    public function retry(): void
    {
        if (! $this->refreshProgress() || ! $this->completed || $this->passed) {
            return;
        }

        $this->attemptNumber++;
        $this->questionIndex = 0;
        $this->selectedAnswerIndex = null;
        $this->correctAnswers = 0;
        $this->completed = false;
        $this->passed = false;
        $this->alarmStopped = false;
        $this->materializeQuestions($this->usedQuestionIds);
        $this->usedQuestionIds = array_merge($this->usedQuestionIds, array_column($this->questions, 'id'));
        $this->saveProgress();
    }

    public function snoozeAlarm(): void
    {
        if (! $this->snoozeAvailable || $this->alarmStopped || ! $this->refreshProgress()) {
            return;
        }

        $execution = AlarmExecution::query()->find($this->executionId);
        if ($execution === null) {
            return;
        }

        $alarm = Alarm::query()->find($this->alarmId);

        if ($this->currentOccurrence() === null) {
            $this->leaveUnavailableChallenge();

            return;
        }

        app(NativeAlarmScheduler::class)->snooze($this->alarmId, $alarm?->snoozeMinutes() ?? 5);
        app(AlarmExecutionLifecycle::class)->snooze($execution);
        $this->replace('/');
    }

    public function turnOffAlarm(): void
    {
        if ($this->alarmStopped || ! $this->refreshProgress() || ! $this->completed || ! $this->passed) {
            return;
        }

        if ($this->currentOccurrence() === null) {
            $this->leaveUnavailableChallenge();

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

        $execution = AlarmExecution::query()->find($this->executionId);
        if ($execution !== null) {
            app(AlarmExecutionLifecycle::class)->complete($execution);
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
            'alarm_id' => $this->alarmId,
            'alarm_execution_id' => $this->executionId,
            'challenge_theme' => $this->challengeTheme,
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
        $theme = $this->challengeTheme;
        $this->questions = $catalog->questions($this->questionCount, $excludedQuestionIds, $preferences->lastChallengeOrder($theme), $theme);
        $preferences->rememberChallengeOrder($theme, $catalog->fingerprint($this->questions));
    }

    private function currentOccurrence(): ?ActiveAlarmOccurrence
    {
        try {
            $occurrence = app(NativeAlarmScheduler::class)->activeRingingOccurrence();

            if ($occurrence === null) {
                return null;
            }

            if (($this->alarmId !== '' && $this->alarmId !== $occurrence->alarmId)
                || ($this->executionId !== '' && $this->executionId !== $occurrence->executionId)) {
                return null;
            }

            return $occurrence;
        } catch (NativeAlarmSchedulingFailed $exception) {
            report($exception);

            return null;
        }
    }

    /** @param array<string, mixed> $progress */
    private function restoreProgress(array $progress): void
    {
        foreach (self::PROGRESS_PROPERTIES as $property) {
            $this->{$property} = $progress[$property];
        }
    }

    private function saveProgress(): void
    {
        $progress = [];
        foreach (self::PROGRESS_PROPERTIES as $property) {
            $progress[$property] = $this->{$property};
        }

        AlarmExecution::query()->whereKey($this->executionId)
            ->where('alarm_id', $this->alarmId)
            ->where('status', 'ringing')
            ->first()?->update(['challenge_progress' => $progress]);
    }

    private function refreshProgress(): bool
    {
        if ($this->unavailable) {
            return false;
        }

        $execution = AlarmExecution::query()->whereKey($this->executionId)
            ->where('alarm_id', $this->alarmId)->first();

        if ($execution === null || $execution->status !== 'ringing') {
            $this->leaveUnavailableChallenge();

            return false;
        }

        if ($execution->challenge_progress !== null) {
            $this->restoreProgress($execution->challenge_progress);
        }

        return true;
    }

    private function leaveUnavailableChallenge(): void
    {
        $this->unavailable = true;
        $this->replace('/');
    }

    public function render(): View
    {
        return view('native.challenge');
    }
}
