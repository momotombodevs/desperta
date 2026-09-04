<native:top-bar :title="$isEditing ? __('app.edit_alarm') : __('app.new_alarm')" display-mode="inline"
                show-navigation-icon/>

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column ref="alarm-editor-screen" class="w-full gap-5 p-5">
        <native:column class="w-full gap-2 rounded-2xl bg-theme-sunrise/15 p-5">
            <native:text font="accent"
                         class="text-xl text-theme-on-background">{{ __('app.morning_intention') }}</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.alarm_android') }}</native:text>
        </native:column>

        <native:date-picker
            :label="__('app.time')"
            mode="time"
            hour-format="12"
            :title="__('app.choose_alarm_time')"
            :confirm-label="__('app.accept')"
            :cancel-label="__('app.cancel')"
            native:model="time"
            :a11y-label="__('app.alarm_time')"
        />
        <native:outlined-text-input :label="__('app.name')" native:model.live="label"
                                    :a11y-label="__('app.alarm_name')"/>

        <native:column class="w-full gap-3">
            <native:text font="accent" class="text-base text-theme-on-background">{{ __('app.repeat') }}</native:text>
            <native:row class="w-full gap-2">
                <native:chip :label="__('app.weekdays.monday.short')" native:model="monday" :a11y-label="__('app.weekdays.monday.label')"/>
                <native:chip :label="__('app.weekdays.tuesday.short')" native:model="tuesday" :a11y-label="__('app.weekdays.tuesday.label')"/>
                <native:chip :label="__('app.weekdays.wednesday.short')" native:model="wednesday" :a11y-label="__('app.weekdays.wednesday.label')"/>
                <native:chip :label="__('app.weekdays.thursday.short')" native:model="thursday" :a11y-label="__('app.weekdays.thursday.label')"/>
                <native:chip :label="__('app.weekdays.friday.short')" native:model="friday" :a11y-label="__('app.weekdays.friday.label')"/>
                <native:chip :label="__('app.weekdays.saturday.short')" native:model="saturday" :a11y-label="__('app.weekdays.saturday.label')"/>
                <native:chip :label="__('app.weekdays.sunday.short')" native:model="sunday" :a11y-label="__('app.weekdays.sunday.label')"/>
            </native:row>
        </native:column>

        <native:toggle :label="__('app.vibration')" native:model="vibration"/>
        @android
            <native:toggle :label="__('app.snooze')" native:model="snoozeEnabled"/>
            @if ($snoozeEnabled)
                <native:select ref="snooze-minutes" :label="__('app.snooze_duration')" :options="['5', '10', '15']"
                               native:model="snoozeMinutes" :a11y-label="__('app.snooze_duration')"/>
            @endif
        @endandroid
        <native:toggle :label="__('app.alarm_active')" native:model="enabled"/>
        <native:select :label="__('app.difficulty')" :options="[__('app.easy'), __('app.normal'), __('app.hard')]"
                       native:model="difficultyDisplay"/>

        <native:row class="w-full gap-3">
            @if ($isEditing)
                <native:button ref="cancel-alarm" variant="secondary" @tap="cancel" size="lg" class="flex-1"
                               :disabled="$awaitingPermission">{{ __('app.cancel') }}</native:button>
            @endif
            <native:button ref="save-alarm" variant="primary" @tap="save" size="lg" class="flex-1"
                           :disabled="$awaitingPermission"
                           :a11y-label="$isEditing ? __('app.save_alarm_changes_a11y') : __('app.save_alarm')">
                {{ $isEditing ? __('app.save_alarm_changes') : __('app.save_alarm') }}
            </native:button>
        </native:row>
    </native:column>
</native:scroll-view>
