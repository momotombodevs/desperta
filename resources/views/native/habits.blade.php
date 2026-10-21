@use('App\Application\AlarmAnalytics\DayStatus')
@use('App\Icons\Android')

<native:top-bar :title="__('app.habits')" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-5 p-5">
        <native:column class="w-full gap-2 rounded-2xl bg-theme-sunrise p-6">
            <native:text font="accent" class="text-sm text-theme-on-sunrise">{{ __('app.current_streak') }}</native:text>
            <native:text font="accent" class="text-5xl text-theme-on-sunrise">{{ $this->habits['current_streak_label'] }}</native:text>
            <native:text class="text-base text-theme-on-sunrise">
                {{ __('app.habits_on_time_summary', ['on_time' => $this->habits['on_time_count'], 'resolved' => $this->habits['resolved_count']]) }}
            </native:text>
            <native:row ref="streak-protector" class="items-center gap-2" :a11y-label="__('app.streak_protector_a11y')">
                <native:icon :android="Android::Shield" name="shield" class="text-theme-on-sunrise" size="18"
                             :opacity="$this->habits['protector_available'] ? 1 : 0.5"/>
                <native:text class="text-sm text-theme-on-sunrise">
                    {{ $this->habits['protector_available'] ? __('app.streak_protector_available') : __('app.streak_protector_used') }}
                </native:text>
            </native:row>
        </native:column>

        <native:column class="w-full gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-4">
            <native:text font="accent" class="text-lg text-theme-on-surface">{{ __('app.weekly_goal_title') }}</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.weekly_goal_progress', ['completed' => $this->weeklyProgress['completed'], 'goal' => $this->weeklyProgress['goal']]) }}</native:text>
            <native:progress-bar :value="min(1, $this->weeklyProgress['completed'] / $this->weeklyProgress['goal'])" />
            <native:select ref="weekly-goal" :label="__('app.weekly_goal_label')"
                           :options="array_map(fn (int $days): string => $days === 1 ? __('app.weekly_goal_day', ['count' => $days]) : __('app.weekly_goal_days', ['count' => $days]), range(1, 7))"
                           :value="$weeklyGoalSelection === '1' ? __('app.weekly_goal_day', ['count' => 1]) : __('app.weekly_goal_days', ['count' => $weeklyGoalSelection])"
                           @change="selectWeeklyGoal" :a11y-label="__('app.weekly_goal_label')" />
        </native:column>

        <native:column class="w-full gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-4">
            <native:text font="accent" class="text-lg text-theme-on-surface">{{ __('app.weekly_summary_title') }}</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.weekly_summary_metrics', ['rate' => $this->weeklyProgress['on_time_rate'], 'snoozes' => $this->habits['weekly_summary']['snooze_count'], 'failures' => $this->habits['weekly_summary']['late_count'] + $this->habits['weekly_summary']['missed_count']]) }}</native:text>
            @if ($this->weeklyProgress['hardest_day'] !== null)
                <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.weekly_hardest_day', ['day' => $this->weeklyProgress['hardest_day']]) }}</native:text>
            @endif
            <native:button ref="share-weekly-summary" variant="secondary" class="w-full" @tap="shareWeeklyProgress"
                           :a11y-label="__('app.share_weekly_progress')">{{ __('app.share_weekly_progress') }}</native:button>
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
                    <native:text font="accent" class="text-2xl text-theme-on-surface">{{ $this->habits['best_streak_label'] }}</native:text>
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
                        <native:column key="recent-day-{{ $day['date'] }}" class="flex-1 items-center gap-1">
                            <native:icon name="circle" size="18" class="{{ DayStatus::from($day['status'])->dotClass() }}" :a11y-label="__('app.habit_day_'.$day['status'])" />
                            <native:text class="text-xs text-theme-on-surface-variant">{{ $day['label'] }}</native:text>
                        </native:column>
                    @endforeach
                </native:row>
            </native:column>
            <native:column ref="habits-calendar" class="w-full gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-4"
                           :a11y-label="__('app.habits_calendar_a11y', ['month' => $this->calendarTitle])">
                <native:row class="w-full items-center gap-2">
                    <native:pressable ref="calendar-previous" class="rounded-full p-2" @tap="showPreviousMonth" :a11y-label="__('app.previous_month')">
                        <native:icon :android="Android::ChevronLeft" name="chevron_left" class="text-theme-on-surface" size="22"/>
                    </native:pressable>
                    <native:text font="accent" class="flex-1 text-lg text-center text-theme-on-surface">{{ $this->calendarTitle }}</native:text>
                    <native:pressable ref="calendar-next" class="rounded-full p-2" @tap="showNextMonth"
                                      :opacity="$this->isShowingCurrentMonth ? 0.3 : 1" :a11y-label="__('app.next_month')">
                        <native:icon :android="Android::ChevronRight" name="chevron_right" class="text-theme-on-surface" size="22"/>
                    </native:pressable>
                </native:row>
                <native:row class="w-full gap-1">
                    @foreach ($this->weekdayInitials as $weekday => $initial)
                        <native:text key="calendar-heading-{{ $weekday }}" class="flex-1 text-xs text-center text-theme-on-surface-variant">{{ $initial }}</native:text>
                    @endforeach
                </native:row>
                @foreach ($this->calendarWeeks as $week)
                    <native:row key="calendar-week-{{ $week[0]['date'] }}" class="w-full gap-1">
                        @foreach ($week as $day)
                            @php($dayStatus = DayStatus::from($day['status']))
                            <native:column key="calendar-day-{{ $day['date'] }}"
                                           class="flex-1 h-10 items-center justify-center rounded-lg {{ $dayStatus->cellClass() }}"
                                           :opacity="$day['future'] || ! $day['in_month'] ? 0.35 : 1"
                                           :a11y-label="$day['day'].': '.($day['protected'] ? __('app.habit_day_protected') : __('app.habit_day_'.$day['status']))">
                                @if ($day['protected'])
                                    <native:icon :android="Android::Shield" name="shield" class="{{ $dayStatus->cellTextClass() }}" size="16"/>
                                @else
                                    <native:text class="text-xs {{ $dayStatus->cellTextClass() }}">{{ $day['day'] }}</native:text>
                                @endif
                            </native:column>
                        @endforeach
                    </native:row>
                @endforeach
            </native:column>
            @if ($this->routineCompletion['started'] > 0)
                <native:column ref="routine-completion" class="w-full gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                    <native:row class="w-full items-center gap-2">
                        <native:icon :android="Android::SelfImprovement" name="self_improvement" class="text-theme-primary" size="22"/>
                        <native:text font="accent" class="flex-1 text-lg text-theme-on-surface">{{ __('app.routine_completion_title') }}</native:text>
                    </native:row>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.routine_completion_summary', $this->routineCompletion) }}</native:text>
                    <native:progress-bar :value="$this->routineCompletion['completed'] / $this->routineCompletion['started']"/>
                </native:column>
            @endif
            @if ($this->wakeConsistency !== null || $this->actualSleep !== null || $this->plannedSleep !== null)
                <native:column ref="wake-consistency" class="w-full gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                    <native:row class="w-full items-center gap-2">
                        <native:icon :android="Android::Bedtime" name="bedtime" class="text-theme-primary" size="22"/>
                        <native:text font="accent" class="flex-1 text-lg text-theme-on-surface">{{ __('app.wake_consistency_title') }}</native:text>
                        @if ($this->wakeConsistency !== null)
                            <native:text font="accent" class="text-base text-theme-primary">{{ __('app.wake_consistency_variation', ['minutes' => $this->wakeConsistency['variation_minutes']]) }}</native:text>
                        @endif
                    </native:row>
                    @if ($this->wakeConsistency !== null)
                        <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.wake_consistency_average', ['minutes' => $this->wakeConsistency['average_delay_minutes']]) }}</native:text>
                    @endif
                    @if ($this->actualSleep !== null)
                        <native:text ref="actual-sleep" class="text-sm text-theme-on-surface">{{ __('app.sleep_actual', $this->actualSleep) }}</native:text>
                    @endif
                    @if ($this->plannedSleep !== null)
                        <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.wake_consistency_sleep', $this->plannedSleep) }}</native:text>
                    @endif
                </native:column>
            @endif
            <native:column class="w-full gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-4">
                <native:text font="accent" class="text-lg text-theme-on-surface">{{ __('app.habits_daily_results') }}</native:text>
                <native:bar-chart
                    class="w-full h-72"
                    :series="$this->dailySeries"
                    :legend="['visible' => true, 'position' => 'bottom']"
                    :y-axis="['minimum' => 0, 'maximum' => $this->dailyChartMaximum, 'interval' => 1, 'maximumFractionDigits' => 0]"
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
