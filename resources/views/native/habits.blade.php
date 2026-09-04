<native:top-bar :title="__('app.habits')" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-5 p-5">
        <native:column class="w-full gap-2 rounded-2xl bg-theme-sunrise p-6">
            <native:text font="accent" class="text-sm text-theme-on-sunrise">{{ __('app.current_streak') }}</native:text>
            <native:text font="accent" class="text-5xl text-theme-on-sunrise">{{ $this->habits['current_streak'] }} {{ __('app.days') }}</native:text>
            <native:text class="text-base text-theme-on-sunrise">
                {{ __('app.habits_on_time_summary', ['on_time' => $this->habits['on_time_count'], 'resolved' => $this->habits['resolved_count']]) }}
            </native:text>
        </native:column>

        @if ($this->habits['resolved_count'] === 0)
            <native:column class="w-full items-center gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-8">
                <native:icon name="history" class="text-theme-secondary" size="40" :a11y-label="__('app.habits_empty')" />
                <native:text font="accent" class="text-lg text-center text-theme-on-surface">{{ __('app.habits_empty_title') }}</native:text>
            </native:column>
        @else
            <native:row class="w-full gap-3">
                <native:column class="flex-1 gap-1 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                    <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.best_streak') }}</native:text>
                    <native:text font="accent" class="text-2xl text-theme-on-surface">{{ $this->habits['best_streak'] }} {{ __('app.days') }}</native:text>
                </native:column>
                <native:column class="flex-1 gap-1 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                    <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.on_time') }}</native:text>
                    <native:text font="accent" class="text-2xl text-theme-on-surface">{{ $this->habits['on_time_rate'] }}%</native:text>
                </native:column>
            </native:row>
            <native:column class="w-full gap-1 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.without_snooze') }}</native:text>
                <native:text font="accent" class="text-2xl text-theme-on-surface">{{ $this->habits['without_snooze_rate'] }}%</native:text>
            </native:column>
            <native:column class="w-full gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                <native:text font="accent" class="text-lg text-theme-on-surface">{{ __('app.habits_last_7_days') }}</native:text>
                <native:row class="w-full gap-2">
                    @foreach ($this->habits['days'] as $day)
                        <native:column class="flex-1 items-center gap-1">
                            <native:icon name="circle" size="18" class="{{ match ($day['status']) { 'on_time' => 'text-theme-success', 'late' => 'text-theme-warning', 'missed' => 'text-theme-destructive', 'pending' => 'text-theme-secondary', default => 'text-theme-outline' } }}" :a11y-label="__('app.habit_day_'.$day['status'])" />
                            <native:text class="text-xs text-theme-on-surface-variant">{{ \Carbon\CarbonImmutable::parse($day['date'])->locale(app()->getLocale())->isoFormat('dd') }}</native:text>
                        </native:column>
                    @endforeach
                </native:row>
            </native:column>
            <native:column class="w-full gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                <native:text font="accent" class="text-lg text-theme-on-surface">{{ __('app.habits_daily_results') }}</native:text>
                <native:bar-chart
                    class="w-full h-72"
                    :series="$this->dailySeries"
                    :legend="['visible' => true, 'position' => 'bottom']"
                    :y-axis="['minimum' => 0, 'maximumFractionDigits' => 0]"
                    :style="['bar' => ['radius' => 6], 'grid' => ['color' => theme('outline')]]"
                    :locale="str_replace('_', '-', app()->getLocale())"
                    :a11y-label="__('app.habits_daily_results_a11y')"
                />
            </native:column>
            <native:column class="w-full gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                <native:text font="accent" class="text-lg text-theme-on-surface">{{ __('app.habits_outcome_distribution') }}</native:text>
                <native:donut-chart
                    class="w-full h-72"
                    :segments="$this->outcomeSegments"
                    :legend="['visible' => true, 'position' => 'bottom']"
                    :inner-radius-ratio="0.62"
                    :style="['segment' => ['gap' => 2, 'cornerRadius' => 7]]"
                    :locale="str_replace('_', '-', app()->getLocale())"
                    :a11y-label="__('app.habits_outcome_distribution_a11y')"
                />
            </native:column>
        @endif
    </native:column>
</native:scroll-view>
