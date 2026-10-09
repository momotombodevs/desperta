<?php

namespace App\Application\Challenges;

use App\Models\Alarm;
use App\Models\AlarmChallengeAttempt;
use App\Models\AlarmExecution;
use Illuminate\Support\Collection;

final class AdaptiveChallengeDifficulty
{
    private const int FastResolutionSeconds = 180;

    public function forAlarm(Alarm $alarm): ChallengeDifficulty
    {
        $executions = AlarmExecution::query()
            ->where('alarm_id', $alarm->id)
            ->where('status', 'completed')
            ->whereNotNull('finished_at')
            ->whereNotNull('started_at')
            ->latest('finished_at')
            ->limit(3)
            ->get()
            ->sortBy('finished_at')
            ->values();

        if ($executions->isEmpty()) {
            return $alarm->challengeDifficulty();
        }

        $attempts = AlarmChallengeAttempt::query()
            ->where('alarm_id', $alarm->id)
            ->whereIn('alarm_execution_id', $executions->pluck('id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('alarm_execution_id');

        $latestAttempts = $attempts->flatten()->sortBy([
            ['created_at', 'desc'],
            ['id', 'desc'],
        ])->take(2)->values();

        if ($latestAttempts->count() === 2 && $latestAttempts->every(fn (AlarmChallengeAttempt $attempt): bool => ! $attempt->passed)) {
            return $this->stepDown($alarm->challengeDifficulty());
        }

        if ($executions->count() === 3 && $this->hasConsistentFastSuccesses($executions, $attempts)) {
            return $this->stepUp($alarm->challengeDifficulty());
        }

        return $alarm->challengeDifficulty();
    }

    /**
     * @param  Collection<int, AlarmExecution>  $executions
     * @param  Collection<string, Collection<int, AlarmChallengeAttempt>>  $attempts
     */
    private function hasConsistentFastSuccesses(Collection $executions, Collection $attempts): bool
    {
        return $executions->every(function (AlarmExecution $execution) use ($attempts): bool {
            $executionAttempts = $attempts->get($execution->id, collect());
            $durationSeconds = $execution->started_at->diffInSeconds($execution->finished_at);

            return $executionAttempts->count() === 1
                && $executionAttempts->first()->passed
                && $executionAttempts->first()->correct_answers === $executionAttempts->first()->question_count
                && $execution->snooze_count === 0
                && $durationSeconds <= self::FastResolutionSeconds;
        });
    }

    private function stepDown(ChallengeDifficulty $difficulty): ChallengeDifficulty
    {
        return match ($difficulty) {
            ChallengeDifficulty::Hard => ChallengeDifficulty::Normal,
            ChallengeDifficulty::Normal => ChallengeDifficulty::Easy,
            ChallengeDifficulty::Easy => ChallengeDifficulty::Easy,
        };
    }

    private function stepUp(ChallengeDifficulty $difficulty): ChallengeDifficulty
    {
        return match ($difficulty) {
            ChallengeDifficulty::Easy => ChallengeDifficulty::Normal,
            ChallengeDifficulty::Normal => ChallengeDifficulty::Hard,
            ChallengeDifficulty::Hard => ChallengeDifficulty::Hard,
        };
    }
}
