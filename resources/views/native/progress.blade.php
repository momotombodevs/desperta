@use('App\Application\PlayerProgress\PlayerProgress')
@use('App\Icons\Android')

<native:top-bar :title="__('progress.title')" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column ref="progress-screen" class="w-full gap-5 p-5">
        <native:column class="w-full gap-3 rounded-2xl bg-theme-sunrise p-6">
            <native:row class="w-full items-center gap-3">
                <native:icon :android="Android::EmojiEvents" name="emoji_events" class="text-theme-on-sunrise" size="40"
                             :scale="1.08" animate-loop="true" :animate-duration="1200" animate-easing="ease-in-out"/>
                <native:column class="flex-1 gap-1">
                    <native:text font="accent" class="text-sm text-theme-on-sunrise">{{ __('progress.level', ['level' => $this->summary['level']]) }}</native:text>
                    <native:text font="accent" class="text-3xl text-theme-on-sunrise">{{ $this->summary['title'] }}</native:text>
                </native:column>
            </native:row>
            <native:text ref="progress-xp" font="accent" content-transition="numeric" class="text-5xl text-theme-on-sunrise">{{ $this->summary['xp'] }}</native:text>
            <native:text class="text-sm text-theme-on-sunrise">{{ __('progress.xp', ['xp' => $this->summary['xp']]) }}</native:text>
            <native:progress-bar :value="$this->summary['progress']" :color="theme('on-sunrise')"/>
            <native:text class="text-sm text-theme-on-sunrise">
                {{ $this->summary['next_level_xp'] === null
                    ? __('progress.max_level')
                    : __('progress.xp_to_next', ['xp' => $this->summary['next_level_xp'] - $this->summary['xp'], 'level' => $this->summary['level'] + 1]) }}
            </native:text>
        </native:column>

        <native:navigation-row key="open-daily-question" target="open-daily-question" url="/settings/daily-question"
                               :icon="Android::Quiz->value" :title="__('progress.daily_question.title')"
                               :subtitle="__('progress.daily_question.reward', ['xp' => PlayerProgress::DailyQuestionXp])"/>

        @php($unlockedCount = count(array_filter($this->achievements, fn (array $entry): bool => $entry['unlocked'])))
        <native:column class="w-full gap-3">
            <native:row class="w-full items-center">
                <native:text font="accent" class="flex-1 text-lg text-theme-on-background">{{ __('progress.achievements_title') }}</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">{{ __('progress.achievements_summary', ['unlocked' => $unlockedCount, 'total' => count($this->achievements)]) }}</native:text>
            </native:row>

            <native:column class="w-full gap-3" :a11y-label="__('progress.achievements_list')">
                @foreach ($this->achievements as $entry)
                    @php($achievement = $entry['achievement'])
                    <native:row ref="achievement-{{ $achievement->value }}" key="achievement-{{ $achievement->value }}"
                                class="w-full items-center gap-3 rounded-2xl border p-4 {{ $entry['unlocked'] ? 'border-theme-sunrise bg-theme-sunrise/15' : 'border-theme-outline bg-theme-surface' }}"
                                :opacity="$entry['unlocked'] ? 1 : 0.6"
                                :a11y-label="$achievement->title().'. '.($entry['unlocked'] ? __('progress.unlocked') : __('progress.locked'))">
                        <native:icon :android="$achievement->icon()" class="{{ $entry['unlocked'] ? 'text-theme-sunrise' : 'text-theme-on-surface-variant' }}" size="32"/>
                        <native:column class="flex-1 gap-1">
                            <native:text font="accent" class="text-base text-theme-on-surface">{{ $achievement->title() }}</native:text>
                            <native:text class="text-sm text-theme-on-surface-variant">{{ $achievement->description() }}</native:text>
                            @unless ($entry['unlocked'])
                                <native:progress-bar :value="$entry['progress'] / $entry['goal']"/>
                            @endunless
                        </native:column>
                        @if ($entry['unlocked'])
                            <native:icon :android="Android::CheckCircle" name="check_circle" class="text-theme-success" size="24"/>
                        @else
                            <native:text class="text-sm text-theme-on-surface-variant">{{ __('progress.achievement_progress', ['progress' => $entry['progress'], 'goal' => $entry['goal']]) }}</native:text>
                        @endif
                    </native:row>
                @endforeach
            </native:column>
        </native:column>

        <native:column class="w-full gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-4">
            <native:text font="accent" class="text-lg text-theme-on-surface">{{ __('progress.how_to_earn') }}</native:text>
            @foreach ($this->xpRules as $rule => $xp)
                <native:row key="xp-rule-{{ $rule }}" class="w-full items-center gap-2">
                    <native:icon :android="Android::Star" name="star" class="text-theme-sunrise" size="16"/>
                    <native:text class="flex-1 text-sm text-theme-on-surface-variant">{{ __('progress.rules.'.$rule, ['xp' => $xp]) }}</native:text>
                </native:row>
            @endforeach
        </native:column>
    </native:column>
</native:scroll-view>
