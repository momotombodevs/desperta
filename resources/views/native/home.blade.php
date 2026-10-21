@use('App\Icons\Android')

<native:top-bar :title="__('app.app_name')" :subtitle="__('app.tagline')" display-mode="large">
    <native:top-bar-action
        id="settings"
        ref="settings"
        :android-icon="Android::Settings"
        :label="__('app.settings')"
        @navigate="'/settings'"
        :a11y-label="__('app.settings')"
    />
</native:top-bar>

<native:list ref="alarm-list" class="w-full h-full bg-theme-background p-5">
    @if ($this->nextAlarm !== null)
        <native:column class="w-full gap-2 rounded-2xl bg-theme-sunrise p-6">
            <native:text font="accent" class="text-sm text-theme-on-sunrise">{{ __('app.next_alarm') }}</native:text>
            <native:text font="accent" class="text-5xl text-theme-on-sunrise">
                {{ $this->nextAlarm->displayTime() }}
            </native:text>
            <native:text class="text-base text-theme-on-sunrise">
                {{ $this->nextAlarm->scheduleSummary() }}
            </native:text>
        </native:column>
        <native:spacer :height="24"/>
    @endif

    <native:pressable ref="open-progress" class="w-full rounded-2xl border border-theme-outline bg-theme-surface p-4"
                      @tap="openProgress" :press-scale="0.98"
                      :a11y-label="__('progress.level_with_title', ['level' => $this->playerProgress['level'], 'title' => $this->playerProgress['title']])">
        <native:row class="w-full items-center gap-3">
            <native:icon :android="Android::EmojiEvents" name="emoji_events" class="text-theme-sunrise" size="32"/>
            <native:column class="flex-1 gap-1">
                <native:text font="accent" class="text-base text-theme-on-surface">{{ __('progress.level_with_title', ['level' => $this->playerProgress['level'], 'title' => $this->playerProgress['title']]) }}</native:text>
                <native:progress-bar :value="$this->playerProgress['progress']"/>
            </native:column>
            <native:text ref="home-xp" font="accent" content-transition="numeric" class="text-base text-theme-sunrise">{{ __('progress.xp', ['xp' => $this->playerProgress['xp']]) }}</native:text>
            <native:icon :android="Android::ChevronRight" class="text-theme-on-surface" size="20"/>
        </native:row>
    </native:pressable>
    <native:spacer :height="12"/>
    <native:daily-question-card key="daily-question-card"/>
    <native:spacer :height="24"/>

    @if ($this->alarms->isEmpty())
            <native:column ref="home-screen" class="w-full h-full items-center justify-center gap-3 px-8">
            <native:icon
                :android="Android::AlarmOff"
                :opacity="$emptyStateVisible ? 1 : 0"
                :scale="$emptyStateVisible ? 1 : 0.92"
                :animate-duration="180"
                animate-easing="ease-out"
                class="text-theme-secondary"
                size="96"
                :a11y-label="__('app.no_alarms_a11y')"
            />
            <native:text font="accent" class="text-xl text-center text-theme-on-background">{{ __('app.empty_alarms_title') }}</native:text>
            <native:text class="text-base text-center text-theme-on-surface-variant">{{ __('app.empty_alarms_body') }}</native:text>
        </native:column>
    @else
        <native:column ref="home-screen" class="w-full gap-3">
            <native:text font="accent"
                         class="text-lg text-theme-on-background">{{ __('app.your_alarms') }}</native:text>
        </native:column>
        <native:spacer :height="12"/>

        @foreach ($this->alarms as $alarm)
            @php($isRinging = $activeAlarmId === $alarm->id)
            <native:list-item ref="edit-alarm-{{ $alarm->id }}" key="alarm-{{ $alarm->id }}"
                              :class="$isRinging ? 'w-full rounded-xl border border-theme-warning bg-theme-warning' : 'w-full rounded-xl border border-theme-outline bg-theme-surface'"
                              :headline="$alarm->displayTime()" :supporting="$isRinging ? __('app.alarm_ringing_continue') : $alarm->scheduleSummary()"
                              leadingIcon="alarm" :leadingIconColor="theme($isRinging ? 'on-warning' : 'primary')"
                              :containerColor="theme($isRinging ? 'warning' : 'surface')"
                              :headlineColor="theme($isRinging ? 'on-warning' : 'on-surface')"
                              :supportingColor="theme($isRinging ? 'on-warning' : 'on-surface-variant')"
                              :trailingSwitch="$alarm->enabled"
                              on-trailing-change="toggleAlarm('{{ $alarm->id }}')"
                              :trailing-a11y-label="__('app.alarm_active')"
                              @tap="editAlarm('{{ $alarm->id }}')"
                              on-swipe-delete="confirmDeleteAlarm('{{ $alarm->id }}')"
                              :a11y-label="($isRinging ? __('app.alarm_ringing_continue') : __('app.edit_alarm')).' '.$alarm->displayTime()"
                              :a11y-hint="__('app.swipe_to_delete')"/>
            @if (! $loop->last)
                <native:spacer :height="12"/>
            @endif
        @endforeach
    @endif

</native:list>

<native:bottom-bar>
    <native:column class="w-full bg-theme-background p-4">
        <native:button ref="create-alarm" variant="primary" size="lg" class="w-full" @tap="createAlarm"
                       :a11y-label="__('app.create_alarm')">{{ __('app.create_alarm') }}</native:button>
    </native:column>
</native:bottom-bar>
