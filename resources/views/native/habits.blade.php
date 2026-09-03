<native:top-bar :title="__('app.habits')" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-5 p-5">
        <native:column class="w-full gap-2 rounded-2xl bg-theme-sunrise p-6">
            <native:text font="accent" class="text-sm text-theme-on-sunrise">{{ __('app.habits_last_7_days') }}</native:text>
            <native:text font="accent" class="text-5xl text-theme-on-sunrise">{{ $this->habits['completion_rate'] }}%</native:text>
            <native:text class="text-base text-theme-on-sunrise">
                {{ __('app.habits_completion_summary', ['completed' => $this->habits['completed'], 'total' => $this->habits['total']]) }}
            </native:text>
        </native:column>

        @if ($this->habits['total'] === 0)
            <native:column class="w-full items-center gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-8">
                <native:icon name="history" class="text-theme-secondary" size="40" :a11y-label="__('app.habits_empty')" />
                <native:text font="accent" class="text-lg text-center text-theme-on-surface">{{ __('app.habits_empty_title') }}</native:text>
                <native:text class="text-base text-center text-theme-on-surface-variant">{{ __('app.habits_empty') }}</native:text>
            </native:column>
        @else
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
