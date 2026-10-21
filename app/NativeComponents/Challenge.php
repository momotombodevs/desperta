<?php

namespace App\NativeComponents;

use App\AlarmScheduling\ActiveAlarmOccurrence;
use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\AlarmScheduling\AlarmExecutionLifecycle;
use App\Application\AlarmScheduling\NativeAlarmScheduler;
use App\Application\Challenges\AdaptiveChallengeDifficulty;
use App\Application\Challenges\ChallengeCatalog;
use App\Application\Challenges\ChallengeDifficulty;
use App\Application\Challenges\ChallengeTheme;
use App\Application\Challenges\ChallengeType;
use App\Application\MorningRoutine\MorningRoutineManager;
use App\Application\PlayerProgress\Achievement;
use App\Application\PlayerProgress\PlayerProgress;
use App\Application\Preferences\AppPreferences;
use App\Models\Alarm;
use App\Models\AlarmChallengeAttempt;
use App\Models\AlarmExecution;
use App\Models\AlarmExecutionRoutineStep;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Momotombo\NativePHPAlarms\Events\AppResumed;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Device;

class Challenge extends NativeComponent
{
    use ResumesActiveAlarm;

    public string $alarmId = '';

    public string $executionId = '';

    public string $challengeTheme = '';

    public string $challengeType = ChallengeType::Trivia->value;

    public string $difficulty = '';

    public string $memoryPhase = 'memorize';

    public bool $unavailable = false;

    /** @var list<string> */
    private const array PROGRESS_PROPERTIES = [
        'questions', 'usedQuestionIds', 'questionIndex', 'selectedAnswerIndex',
        'correctAnswers', 'questionCount', 'requiredCorrectAnswers', 'attemptNumber',
        'completed', 'passed', 'challengeTheme', 'challengeType', 'difficulty', 'showRetryHint', 'memoryPhase',
        'answerRevealed', 'comboCount', 'bestCombo', 'memoryDeadline', 'answeredCount',
    ];

    public bool $snoozeAvailable = false;

    public int $snoozeMinutes = 5;

    /** @var list<array{id: string, question: string, options: list<string>, answer: string, instruction?: string, memory_sequence?: ?string, explanation?: ?string}> */
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

    public bool $showRetryHint = false;

    public bool $answerRevealed = false;

    public int $comboCount = 0;

    public int $bestCombo = 0;

    /** Unix timestamp when the memory sequence hides; 0 keeps it visible until the user is ready. */
    public int $memoryDeadline = 0;

    public bool $alarmStopped = false;

    /** Questions answered when the attempt ended; lower than the total when it ended early. */
    public int $answeredCount = 0;

    public int $earnedXp = 0;

    public ?int $reachedLevel = null;

    /** @var list<string> Achievement values unlocked by this wake-up. */
    public array $unlockedAchievements = [];

    public bool $routineSheetVisible = false;

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

        $difficulty = app(AdaptiveChallengeDifficulty::class)->forAlarm($alarm);
        $this->difficulty = $difficulty->value;
        $this->challengeTheme = $alarm->challengeTheme()?->value ?? app(AppPreferences::class)->challengeTheme();
        $this->challengeType = app(ChallengeCatalog::class)->chooseType($alarm->allowedChallengeTypes())->value;
        $this->questionCount = $difficulty->questionCount();
        $this->requiredCorrectAnswers = $difficulty->requiredCorrectAnswers();
        $this->materializeQuestions();
        $this->startMemorizing();
        $this->usedQuestionIds = array_column($this->questions, 'id');

        $this->attemptNumber = AlarmChallengeAttempt::query()
            ->where('alarm_id', $this->alarmId)
            ->where('alarm_execution_id', $this->executionId)
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
            if (! $this->refreshProgress() || $this->completed || ! $this->answerRevealed) {
                return;
            }

            $finishesAttempt = $this->finishesAttempt();
            $this->selectedAnswerIndex = null;
            $this->answerRevealed = false;

            if ($finishesAttempt) {
                $this->answeredCount = $this->questionIndex + 1;
                $this->completed = true;
                $this->passed = $this->correctAnswers >= $this->requiredCorrectAnswers;
                $this->recordAttempt();
            } else {
                $this->questionIndex++;
                $this->startMemorizing();
            }

            $this->saveProgress();
        });

        if ($this->completed && $this->passed && ! $this->unavailable) {
            $this->turnOffAlarm();
        }
    }

    /**
     * Locks in the answer and grades it immediately so the screen can reveal
     * the correct option before moving on.
     */
    public function selectAnswer(int $answerIndex): void
    {
        $answeredCorrectly = DB::transaction(function () use ($answerIndex): ?bool {
            if (! $this->refreshProgress() || $this->completed || $this->answerRevealed || $this->isMemorizing()
                || ! array_key_exists($answerIndex, $this->questions[$this->questionIndex]['options'])) {
                return null;
            }

            $this->selectedAnswerIndex = $answerIndex;
            $this->answerRevealed = true;

            if ($this->isSelectedAnswerCorrect()) {
                $this->correctAnswers++;
                $this->comboCount++;
                $this->bestCombo = max($this->bestCombo, $this->comboCount);
            } else {
                $this->comboCount = 0;
            }

            $this->saveProgress();

            return $this->isSelectedAnswerCorrect();
        });

        if ($answeredCorrectly === false) {
            Device::vibrate();
        }
    }

    public function showMemoryQuestion(): void
    {
        if ($this->challengeType !== ChallengeType::Memory->value || $this->memoryPhase !== 'memorize' || ! $this->refreshProgress()) {
            return;
        }

        $this->memoryPhase = 'answer';
        $this->saveProgress();
    }

    #[Computed]
    public function memorizing(): bool
    {
        return $this->isMemorizing();
    }

    #[Computed]
    public function memorySecondsLeft(): int
    {
        return max(0, $this->memoryDeadline - now()->timestamp);
    }

    /** @return list<Achievement> */
    #[Computed]
    public function newAchievements(): array
    {
        return array_values(array_filter(array_map(fn (string $value): ?Achievement => Achievement::tryFrom($value), $this->unlockedAchievements)));
    }

    #[Computed]
    public function reachedLevelTitle(): string
    {
        return $this->reachedLevel === null ? '' : app(PlayerProgress::class)->levelTitle($this->reachedLevel);
    }

    /** The pack the current trivia question comes from; mixed questions carry it as their id prefix. */
    #[Computed]
    public function currentTopic(): ?ChallengeTheme
    {
        $questionId = (string) ($this->questions[$this->questionIndex]['id'] ?? '');

        return $this->challengeTheme === ChallengeTheme::Mix->value && str_contains($questionId, '.')
            ? ChallengeTheme::tryFrom(strstr($questionId, '.', true))
            : ChallengeTheme::tryFrom($this->challengeTheme);
    }

    /** Whether continuing from the current question ends the attempt, either because it is the last one or the score can no longer pass. */
    #[Computed]
    public function finishesAttempt(): bool
    {
        $remainingQuestions = count($this->questions) - $this->questionIndex - 1;

        return $remainingQuestions === 0 || $this->correctAnswers + $remainingQuestions < $this->requiredCorrectAnswers;
    }

    #[Computed]
    public function answeredCorrectly(): bool
    {
        return $this->answerRevealed && $this->isSelectedAnswerCorrect();
    }

    /** A cheer for a correct answer that changes from question to question. */
    #[Computed]
    public function praise(): string
    {
        $praises = trans('app.answer_praise');

        return $praises[($this->questionIndex + $this->attemptNumber) % count($praises)];
    }

    #[Computed]
    public function endedEarly(): bool
    {
        return $this->completed && $this->answeredCount > 0 && $this->answeredCount < count($this->questions);
    }

    public function retry(): void
    {
        if (! $this->refreshProgress() || ! $this->completed || $this->passed) {
            return;
        }

        $this->attemptNumber++;
        $this->showRetryHint = $this->attemptNumber >= 2;
        $this->questionIndex = 0;
        $this->answeredCount = 0;
        $this->selectedAnswerIndex = null;
        $this->answerRevealed = false;
        $this->correctAnswers = 0;
        $this->comboCount = 0;
        $this->completed = false;
        $this->passed = false;
        $this->alarmStopped = false;
        $this->materializeQuestions($this->usedQuestionIds);
        $this->usedQuestionIds = array_merge($this->usedQuestionIds, array_column($this->questions, 'id'));
        $this->startMemorizing();
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

        $levelBefore = app(PlayerProgress::class)->summary()['level'];
        $execution = AlarmExecution::query()->find($this->executionId);
        if ($execution !== null) {
            app(AlarmExecutionLifecycle::class)->complete($execution);
        }

        $this->alarmStopped = true;
        $this->prepareMorningRoutine();
        $this->celebrateProgress($levelBefore);
    }

    public function returnHome(): void
    {
        if (! $this->alarmStopped) {
            return;
        }

        $this->replace('/');
    }

    public function openMorningRoutine(): void
    {
        if (! $this->alarmStopped || $this->executionId === '' || $this->routineSteps->isEmpty()) {
            return;
        }

        $this->routineSheetVisible = true;
    }

    public function dismissRoutineSheet(): void
    {
        $this->routineSheetVisible = false;
    }

    public function completeRoutineStep(string $stepId): void
    {
        if (! $this->alarmStopped || $this->executionId === '') {
            return;
        }

        $execution = AlarmExecution::query()->find($this->executionId);
        if ($execution === null || $execution->status !== 'completed') {
            return;
        }

        $routine = app(MorningRoutineManager::class);
        $finishesRoutine = $routine->executionSteps($execution)
            ->whereNull('completed_at')
            ->pluck('id')
            ->all() === [$stepId];
        $levelBefore = $finishesRoutine ? app(PlayerProgress::class)->summary()['level'] : 0;

        $routine->completeStep($execution, $stepId);

        if ($finishesRoutine) {
            $this->celebrateProgress($levelBefore);
        }
    }

    /** @return Collection<int, AlarmExecutionRoutineStep> */
    #[Computed]
    public function routineSteps(): Collection
    {
        $execution = $this->executionId === '' ? null : AlarmExecution::query()->find($this->executionId);

        return $execution === null
            ? new Collection
            : app(MorningRoutineManager::class)->executionSteps($execution);
    }

    private function recordAttempt(): void
    {
        AlarmChallengeAttempt::query()->create([
            'alarm_id' => $this->alarmId,
            'alarm_execution_id' => $this->executionId,
            'challenge_theme' => $this->challengeTheme,
            'challenge_type' => $this->challengeType,
            'attempt_number' => $this->attemptNumber,
            'correct_answers' => $this->correctAnswers,
            'question_count' => count($this->questions),
            'required_correct_answers' => $this->requiredCorrectAnswers,
            'passed' => $this->passed,
            'best_combo' => $this->bestCombo,
        ]);
    }

    /** Refreshes the XP, level-up and newly unlocked achievements shown after waking up or finishing the routine. */
    private function celebrateProgress(int $levelBefore): void
    {
        $progress = app(PlayerProgress::class);
        $execution = AlarmExecution::query()->find($this->executionId);
        $this->earnedXp = $execution === null ? 0 : $progress->xpForExecution($execution);
        $levelAfter = $progress->summary()['level'];
        $this->reachedLevel = $levelAfter > $levelBefore ? $levelAfter : $this->reachedLevel;
        $this->unlockedAchievements = array_values(array_unique([
            ...$this->unlockedAchievements,
            ...array_map(fn (Achievement $achievement): string => $achievement->value, $progress->announceNewAchievements()),
        ]));
    }

    private function prepareMorningRoutine(): void
    {
        $execution = $this->executionId === '' ? null : AlarmExecution::query()->find($this->executionId);
        if ($execution === null || $execution->status !== 'completed') {
            return;
        }

        $manager = app(MorningRoutineManager::class);
        $manager->beginForExecution($execution);
        $this->routineSheetVisible = $manager->executionSteps($execution)->isNotEmpty();
    }

    /** @param list<string> $excludedQuestionIds */
    private function materializeQuestions(array $excludedQuestionIds = []): void
    {
        $preferences = app(AppPreferences::class);
        $catalog = app(ChallengeCatalog::class);
        $theme = $this->challengeTheme;
        $type = ChallengeType::from($this->challengeType);
        $difficulty = ChallengeDifficulty::fromStored($this->difficulty);
        if ($type === ChallengeType::Trivia) {
            $questions = $catalog->questions($this->questionCount, $excludedQuestionIds, $preferences->lastChallengeOrder($theme), $theme, $difficulty);
            $this->questions = array_map(fn (array $question): array => $catalog->triviaQuestion($question), $questions);
            $preferences->rememberChallengeOrder($theme, $catalog->fingerprint($this->questions));

            return;
        }

        $this->questions = $catalog->questionsForType($type, $this->questionCount, $difficulty);
    }

    private function isSelectedAnswerCorrect(): bool
    {
        $question = $this->questions[$this->questionIndex] ?? null;

        return $question !== null
            && $this->selectedAnswerIndex !== null
            && ($question['options'][$this->selectedAnswerIndex] ?? null) === $question['answer'];
    }

    private function startMemorizing(): void
    {
        if ($this->challengeType !== ChallengeType::Memory->value) {
            $this->memoryDeadline = 0;

            return;
        }

        $this->memoryPhase = 'memorize';
        $this->memoryDeadline = now()->timestamp + ChallengeDifficulty::fromStored($this->difficulty)->memorizeSeconds();
    }

    private function isMemorizing(): bool
    {
        return $this->challengeType === ChallengeType::Memory->value
            && $this->memoryPhase === 'memorize'
            && ($this->memoryDeadline === 0 || now()->timestamp < $this->memoryDeadline);
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
            if (array_key_exists($property, $progress)) {
                $this->{$property} = $progress[$property];
            }
        }

        $this->questions = array_map(fn (array $question): array => [
            ...$question,
            'instruction' => $question['instruction'] ?? trans('challenges.types.'.$this->challengeType.'.instruction'),
        ], $this->questions);
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
