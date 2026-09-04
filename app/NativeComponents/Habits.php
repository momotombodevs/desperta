<?php

namespace App\NativeComponents;

use App\AlarmScheduling\AlarmOccurrenceReconciler;
use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

final class Habits extends NativeComponent
{
    public function mount(): void
    {
        app(AlarmOccurrenceReconciler::class)->reconcile();
    }

    public function navTitle(): string
    {
        return __('app.habits');
    }

    /**
     * @return array{
     *     current_streak: int,
     *     best_streak: int,
     *     on_time_count: int,
     *     resolved_count: int,
     *     on_time_rate: int,
     *     without_snooze_count: int,
     *     without_snooze_rate: int,
     *     days: list<array{date: string, status: string, on_time: int, late: int, missed: int, pending: int}>
     * }
     */
    #[Computed]
    public function habits(): array
    {
        return app(AlarmHabitsAnalytics::class)->summarize();
    }

    /** @return list<array{id: string, name: string, color: string, points: list<array{id: string, label: string, value: int}>}> */
    #[Computed]
    public function dailySeries(): array
    {
        return [
            $this->series('on_time', __('app.on_time'), theme('success')),
            $this->series('late', __('app.late'), theme('warning')),
            $this->series('missed', __('app.missed'), theme('destructive')),
        ];
    }

    /** @return list<array{id: string, label: string, value: int, color: string}> */
    #[Computed]
    public function outcomeSegments(): array
    {
        return [
            $this->segment('on_time', __('app.on_time'), theme('success')),
            $this->segment('late', __('app.late'), theme('warning')),
            $this->segment('missed', __('app.missed'), theme('destructive')),
        ];
    }

    public function render(): View
    {
        return view('native.habits');
    }

    /** @return array{id: string, name: string, color: string, points: list<array{id: string, label: string, value: int}>} */
    private function series(string $status, string $name, string $color): array
    {
        return [
            'id' => $status,
            'name' => $name,
            'color' => $color,
            'points' => array_map(
                fn (array $day): array => [
                    'id' => "{$status}-{$day['date']}",
                    'label' => CarbonImmutable::parse($day['date'])->locale(app()->getLocale())->isoFormat('dd'),
                    'value' => $day[$status],
                ],
                $this->habits['days'],
            ),
        ];
    }

    /** @return array{id: string, label: string, value: int, color: string} */
    private function segment(string $status, string $label, string $color): array
    {
        return [
            'id' => $status,
            'label' => $label,
            'value' => array_sum(array_column($this->habits['days'], $status)),
            'color' => $color,
        ];
    }
}
