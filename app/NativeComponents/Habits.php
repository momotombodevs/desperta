<?php

namespace App\NativeComponents;

use App\Application\AlarmAnalytics\AlarmHabitsAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

final class Habits extends NativeComponent
{
    public function navTitle(): string
    {
        return __('app.habits');
    }

    /**
     * @return array{
     *     completed: int,
     *     missed: int,
     *     total: int,
     *     completion_rate: int,
     *     days: list<array{date: string, completed: int, missed: int}>
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
            $this->series('completed', __('app.completed'), theme('success')),
            $this->series('missed', __('app.missed'), theme('destructive')),
        ];
    }

    /** @return list<array{id: string, label: string, value: int, color: string}> */
    #[Computed]
    public function outcomeSegments(): array
    {
        return [
            [
                'id' => 'completed',
                'label' => __('app.completed'),
                'value' => $this->habits['completed'],
                'color' => theme('success'),
            ],
            [
                'id' => 'missed',
                'label' => __('app.missed'),
                'value' => $this->habits['missed'],
                'color' => theme('destructive'),
            ],
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
}
