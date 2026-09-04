<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class HabitsDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public const string AlarmLabel = '[DEMO] Historial de hábitos';

    private const int HistoryDays = 180;

    private const string AlarmTime = '06:30';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->remove();

            foreach (array_chunk($this->executions(), 50) as $executions) {
                DB::table('alarm_executions')->insert($executions);
            }
        });
    }

    public function remove(): void
    {
        DB::table('alarm_executions')
            ->whereNull('alarm_id')
            ->where('alarm_label', self::AlarmLabel)
            ->delete();
    }

    /** @return list<array<string, int|string|null>> */
    private function executions(): array
    {
        $latestDay = CarbonImmutable::now((string) config('app.alarm_timezone'))
            ->startOfDay()
            ->subDay();
        $executions = [];

        for ($daysAgo = self::HistoryDays - 1; $daysAgo >= 0; $daysAgo--) {
            $scheduledFor = $latestDay
                ->subDays($daysAgo)
                ->setTimeFromTimeString(self::AlarmTime)
                ->utc();
            $isMissed = $daysAgo % 17 === 5;
            $isLate = ! $isMissed && $daysAgo % 7 === 2;
            $snoozeCount = $isLate ? 2 : (! $isMissed && $daysAgo % 4 === 1 ? 1 : 0);
            $finishedAt = $scheduledFor->addMinutes($isMissed ? 30 : ($isLate ? 18 : 2 + ($daysAgo % 6)));
            $timestamp = $scheduledFor->toDateTimeString();

            $executions[] = [
                'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'desperta-habits-demo-'.$scheduledFor->toDateString())->toString(),
                'alarm_id' => null,
                'alarm_label' => self::AlarmLabel,
                'alarm_time' => self::AlarmTime,
                'status' => $isMissed ? 'missed' : 'completed',
                'scheduled_for' => $timestamp,
                'started_at' => $isMissed ? null : $timestamp,
                'snoozed_at' => $snoozeCount === 0 ? null : $scheduledFor->addMinutes($snoozeCount * 5)->toDateTimeString(),
                'finished_at' => $finishedAt->toDateTimeString(),
                'snooze_count' => $snoozeCount,
                'created_at' => $timestamp,
                'updated_at' => $finishedAt->toDateTimeString(),
            ];
        }

        return $executions;
    }
}
