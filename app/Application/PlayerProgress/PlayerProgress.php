<?php

namespace App\Application\PlayerProgress;

use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use App\Application\Challenges\ChallengeType;
use App\Application\Preferences\AppPreferences;
use App\Models\AlarmChallengeAttempt;
use App\Models\AlarmExecution;
use App\Models\AlarmExecutionRoutineStep;
use App\Models\DailyQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Derives experience, levels and achievements from the local alarm history,
 * so progress is retroactive and never drifts from what actually happened.
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

    public function __construct(
        private readonly AlarmHabitsAnalytics $analytics,
        private readonly AppPreferences $preferences,
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
        $perfectExecutionIds = $this->perfectAttempts()->pluck('alarm_execution_id');
        $routineExecutionIds = $this->completedRoutineExecutionIds();
        $executionXp = $this->completedExecutions()
            ->sum(fn (AlarmExecution $execution): int => $this->executionXp($execution, $perfectExecutionIds, $routineExecutionIds));

        return $executionXp + DailyQuestion::query()->where('correct', true)->count() * self::DailyQuestionXp;
    }

    public function xpForExecution(AlarmExecution $execution): int
    {
        return $this->executionXp($execution, $this->perfectAttempts()->pluck('alarm_execution_id'), $this->completedRoutineExecutionIds());
    }

    /** @return list<array{achievement: Achievement, progress: int, goal: int, unlocked: bool}> */
    public function achievements(): array
    {
        $completed = $this->completedExecutions();
        $bestStreak = $this->analytics->summarize()['best_streak'];
        $progress = [
            Achievement::FirstWake->value => $completed->count(),
            Achievement::Streak3->value => $bestStreak,
            Achievement::Streak7->value => $bestStreak,
            Achievement::Streak30->value => $bestStreak,
            Achievement::NoSnooze10->value => $completed->where('snooze_count', 0)->count(),
            Achievement::Lightning->value => $completed->filter(fn (AlarmExecution $execution): bool => $execution->started_at !== null
                && $execution->started_at->diffInSeconds($execution->finished_at) <= self::LightningSeconds)->count(),
            Achievement::Perfect10->value => $this->perfectAttempts()->count(),
            Achievement::Combo5->value => (int) AlarmChallengeAttempt::query()->max('best_combo'),
            Achievement::Explorer5->value => AlarmChallengeAttempt::query()
                ->where('passed', true)
                ->where('challenge_type', ChallengeType::Trivia->value)
                ->distinct()
                ->count('challenge_theme'),
            Achievement::Routine5->value => $this->completedRoutineExecutionIds()->count(),
            Achievement::DailyQuestion7->value => DailyQuestion::query()->where('correct', true)->count(),
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

    /** @param list<Achievement> $achievements */
    public function markSeen(array $achievements): void
    {
        $this->preferences->rememberSeenAchievements(array_map(fn (Achievement $achievement): string => $achievement->value, $achievements));
    }

    /**
     * @param  Collection<int, string>  $perfectExecutionIds
     * @param  Collection<int, string>  $routineExecutionIds
     */
    private function executionXp(AlarmExecution $execution, Collection $perfectExecutionIds, Collection $routineExecutionIds): int
    {
        if ($execution->status !== 'completed' || $execution->finished_at === null) {
            return 0;
        }

        $xp = $this->analytics->statusFor($execution, CarbonImmutable::now()) === 'on_time' ? self::OnTimeXp : self::LateXp;

        if ($execution->snooze_count === 0) {
            $xp += self::NoSnoozeXp;
        }

        if ($perfectExecutionIds->contains($execution->id)) {
            $xp += self::PerfectChallengeXp;
        }

        if ($routineExecutionIds->contains($execution->id)) {
            $xp += self::RoutineXp;
        }

        return $xp;
    }

    /** @return Collection<int, AlarmExecution> */
    private function completedExecutions(): Collection
    {
        return AlarmExecution::query()
            ->where('status', 'completed')
            ->whereNotNull('finished_at')
            ->get();
    }

    /** @return Collection<int, AlarmChallengeAttempt> */
    private function perfectAttempts(): Collection
    {
        return AlarmChallengeAttempt::query()
            ->where('passed', true)
            ->whereColumn('correct_answers', 'question_count')
            ->get(['id', 'alarm_execution_id']);
    }

    /** @return Collection<int, string> */
    private function completedRoutineExecutionIds(): Collection
    {
        return AlarmExecutionRoutineStep::query()
            ->get(['alarm_execution_id', 'completed_at'])
            ->groupBy('alarm_execution_id')
            ->filter(fn (Collection $steps): bool => $steps->every(fn (AlarmExecutionRoutineStep $step): bool => $step->completed_at !== null))
            ->keys();
    }
}
