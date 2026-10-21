<?php

namespace App\Application\PlayerProgress;

use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Application\Challenges\ChallengeTheme;
use App\Application\Challenges\ChallengeType;
use App\Application\MorningRoutine\MorningRoutineManager;
use App\Application\Preferences\AppPreferences;
use App\Models\AlarmChallengeAttempt;
use App\Models\AlarmExecution;
use App\Models\DailyQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Derives experience, levels and achievements from the local alarm history,
 * so progress is retroactive and never drifts from what actually happened.
 *
 * The history is loaded once per instance; resolve a fresh instance to see new results.
 */
final class PlayerProgress
{
    public const int OnTimeXp = 20;

    public const int LateXp = 5;

    public const int NoSnoozeXp = 10;

    public const int PerfectChallengeXp = 15;

    public const int RoutineXp = 10;

    public const int DailyQuestionXp = 5;

    private const int LightningSeconds = 60;

    /** @var list<int> Minimum XP for each level; index 0 is level 1. */
    public const array LevelThresholds = [0, 100, 250, 500, 900, 1400, 2000];

    /** @var array{executions: Collection<int, AlarmExecution>, perfect_execution_ids: Collection<int, string>, routine_execution_ids: Collection<int, string>, correct_daily_questions: int}|null */
    private ?array $history = null;

    public function __construct(
        private readonly AlarmHabitsAnalytics $analytics,
        private readonly AppPreferences $preferences,
        private readonly MorningRoutineManager $routines,
    ) {}

    /** @return array{xp: int, level: int, title: string, level_floor: int, next_level_xp: ?int, progress: float} */
    public function summary(): array
    {
        $xp = $this->totalXp();
        $level = $this->levelFor($xp);
        $floor = self::LevelThresholds[$level - 1];
        $next = self::LevelThresholds[$level] ?? null;

        return [
            'xp' => $xp,
            'level' => $level,
            'title' => $this->levelTitle($level),
            'level_floor' => $floor,
            'next_level_xp' => $next,
            'progress' => $next === null ? 1.0 : ($xp - $floor) / ($next - $floor),
        ];
    }

    public function levelFor(int $xp): int
    {
        $level = 1;

        foreach (self::LevelThresholds as $index => $threshold) {
            if ($xp >= $threshold) {
                $level = $index + 1;
            }
        }

        return $level;
    }

    public function levelTitle(int $level): string
    {
        return trans('progress.levels.'.min($level, count(self::LevelThresholds)));
    }

    public function totalXp(): int
    {
        $history = $this->history();

        return $history['executions']->sum(fn (AlarmExecution $execution): int => $this->executionXp($execution))
            + $history['correct_daily_questions'] * self::DailyQuestionXp;
    }

    public function xpForExecution(AlarmExecution $execution): int
    {
        return $this->executionXp($execution);
    }

    /** @return list<array{achievement: Achievement, progress: int, goal: int, unlocked: bool}> */
    public function achievements(): array
    {
        $history = $this->history();
        $completed = $history['executions'];
        $bestStreak = $this->analytics->bestStreak();
        $passedTriviaThemes = AlarmChallengeAttempt::query()
            ->where('passed', true)
            ->where('challenge_type', ChallengeType::Trivia->value)
            ->whereIn('challenge_theme', array_column(ChallengeTheme::packs(), 'value'))
            ->distinct()
            ->count('challenge_theme');
        $progress = [
            Achievement::FirstWake->value => $completed->count(),
            Achievement::Streak3->value => $bestStreak,
            Achievement::Streak7->value => $bestStreak,
            Achievement::Streak30->value => $bestStreak,
            Achievement::NoSnoozeWeek->value => $this->analytics->bestNoSnoozeRun(),
            Achievement::Lightning->value => $completed->filter(fn (AlarmExecution $execution): bool => $execution->started_at !== null
                && $execution->started_at->diffInSeconds($execution->finished_at) <= self::LightningSeconds)->count(),
            Achievement::Perfect10->value => $history['perfect_execution_ids']->count(),
            Achievement::Combo5->value => (int) AlarmChallengeAttempt::query()->max('best_combo'),
            Achievement::Explorer5->value => $passedTriviaThemes,
            Achievement::AllTopics->value => $passedTriviaThemes,
            Achievement::Routine5->value => $history['routine_execution_ids']->count(),
            Achievement::DailyQuestion7->value => $history['correct_daily_questions'],
        ];

        return array_map(fn (Achievement $achievement): array => [
            'achievement' => $achievement,
            'progress' => min($progress[$achievement->value], $achievement->goal()),
            'goal' => $achievement->goal(),
            'unlocked' => $progress[$achievement->value] >= $achievement->goal(),
        ], Achievement::cases());
    }

    /** @return list<Achievement> */
    public function unseenAchievements(): array
    {
        $seen = $this->preferences->seenAchievements();

        return array_values(array_map(
            fn (array $entry): Achievement => $entry['achievement'],
            array_filter($this->achievements(), fn (array $entry): bool => $entry['unlocked'] && ! in_array($entry['achievement']->value, $seen, true)),
        ));
    }

    /**
     * Returns the achievements unlocked since they were last shown and remembers them as shown.
     *
     * @return list<Achievement>
     */
    public function announceNewAchievements(): array
    {
        $unlocked = $this->unseenAchievements();
        $this->markSeen($unlocked);

        return $unlocked;
    }

    /** @param list<Achievement> $achievements */
    public function markSeen(array $achievements): void
    {
        $this->preferences->rememberSeenAchievements(array_map(fn (Achievement $achievement): string => $achievement->value, $achievements));
    }

    private function executionXp(AlarmExecution $execution): int
    {
        if ($execution->status !== 'completed' || $execution->finished_at === null) {
            return 0;
        }

        $history = $this->history();
        $now = CarbonImmutable::now((string) config('app.alarm_timezone'));
        $xp = $this->analytics->statusFor($execution, $now) === 'on_time' ? self::OnTimeXp : self::LateXp;

        if ($execution->snooze_count === 0) {
            $xp += self::NoSnoozeXp;
        }

        if ($history['perfect_execution_ids']->contains($execution->id)) {
            $xp += self::PerfectChallengeXp;
        }

        if ($history['routine_execution_ids']->contains($execution->id)) {
            $xp += self::RoutineXp;
        }

        return $xp;
    }

    /** @return array{executions: Collection<int, AlarmExecution>, perfect_execution_ids: Collection<int, string>, routine_execution_ids: Collection<int, string>, correct_daily_questions: int} */
    private function history(): array
    {
        return $this->history ??= [
            'executions' => AlarmExecution::query()
                ->where('status', 'completed')
                ->whereNotNull('finished_at')
                ->get()
                ->toBase(),
            'perfect_execution_ids' => AlarmChallengeAttempt::query()
                ->where('passed', true)
                ->whereColumn('correct_answers', 'question_count')
                ->pluck('alarm_execution_id')
                ->unique()
                ->values()
                ->toBase(),
            'routine_execution_ids' => $this->routines->routineOutcomes()->filter()->keys(),
            'correct_daily_questions' => DailyQuestion::query()->where('correct', true)->count(),
        ];
    }
}
