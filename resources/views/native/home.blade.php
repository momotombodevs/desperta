@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar :title="__('app.app_name')" :subtitle="__('app.tagline')" display-mode="large">
    <native:top-bar-action
        id="settings"
        ref="settings"
        :ios-icon="Ios::Gearshape"
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
                {{ $this->nextAlarm->label }} · {{ $this->nextAlarm->repeatLabel() }}
            </native:text>
        </native:column>
        <native:spacer :height="24"/>
    @endif

    @if ($this->alarms->isEmpty())
        <native:column ref="home-screen" class="w-full h-full items-center justify-center">
            <native:icon
                :ios="Ios::BellSlash"
                :android="Android::AlarmOff"
                :opacity="$emptyStateVisible ? 1 : 0"
                :scale="$emptyStateVisible ? 1 : 0.92"
                :animate-duration="180"
                animate-easing="ease-out"
                class="text-theme-secondary"
                size="96"
                :a11y-label="__('app.no_alarms_a11y')"
            />
        </native:column>
    @else
        <native:column ref="home-screen" class="w-full gap-3">
            <native:text font="accent"
                         class="text-lg text-theme-on-background">{{ __('app.your_alarms') }}</native:text>
        </native:column>
        <native:spacer :height="12"/>

        @foreach ($this->alarms as $alarm)
            <native:list-item ref="edit-alarm-{{ $alarm->id }}" key="alarm-{{ $alarm->id }}"
                              class="w-full rounded-xl border border-theme-outline bg-theme-surface"
                              :headline="$alarm->displayTime()" :supporting="$alarm->label.' · '.$alarm->repeatLabel()"
                              leadingIcon="alarm" :leadingIconColor="theme('primary')"
                              :containerColor="theme('surface')"
                              :trailingSwitch="$alarm->enabled"
                              on-trailing-change="toggleAlarm('{{ $alarm->id }}')"
                              :trailing-a11y-label="__('app.alarm_active')"
                              @tap="editAlarm('{{ $alarm->id }}')"
                              on-swipe-delete="confirmDeleteAlarm('{{ $alarm->id }}')"
                              :a11y-label="__('app.edit_alarm').' '.$alarm->displayTime()"
                              :a11y-hint="__('app.swipe_to_delete')"/>
            @if (! $loop->last)
                <native:spacer :height="12"/>
            @endif
        @endforeach
    @endif

</native:list>

<native:fab ref="create-alarm" :ios-icon="Ios::Plus" :android-icon="Android::Add" :label="__('app.create_alarm')"
            @tap="createAlarm" :a11y-label="__('app.create_alarm')"/>
