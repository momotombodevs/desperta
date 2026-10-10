@use('App\Icons\Android')

<native:top-bar :title="__('app.bedtime_reminder')" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-5">
        <native:row class="w-full items-center gap-3 rounded-2xl bg-theme-sunrise/15 p-5">
            <native:icon :android="Android::Bedtime" class="text-theme-sunrise" size="24" />
            <native:text class="flex-1 text-sm text-theme-on-surface">{{ __('app.bedtime_reminder_explainer') }}</native:text>
        </native:row>
        <native:toggle :label="__('app.bedtime_reminder_enabled')" native:model="enabled" />
        @if ($enabled)
            <native:date-picker :label="__('app.bedtime_reminder_time')" mode="time" hour-format="12"
                                native:model="time" :title="__('app.bedtime_reminder_time')"
                                :confirm-label="__('app.accept')" :cancel-label="__('app.cancel')" />
            <native:row class="w-full gap-2">
                <native:chip :label="__('app.weekday_abbreviations.1')" native:model="monday" :a11y-label="__('app.weekday_names.1')" />
                <native:chip :label="__('app.weekday_abbreviations.2')" native:model="tuesday" :a11y-label="__('app.weekday_names.2')" />
                <native:chip :label="__('app.weekday_abbreviations.3')" native:model="wednesday" :a11y-label="__('app.weekday_names.3')" />
                <native:chip :label="__('app.weekday_abbreviations.4')" native:model="thursday" :a11y-label="__('app.weekday_names.4')" />
                <native:chip :label="__('app.weekday_abbreviations.5')" native:model="friday" :a11y-label="__('app.weekday_names.5')" />
                <native:chip :label="__('app.weekday_abbreviations.6')" native:model="saturday" :a11y-label="__('app.weekday_names.6')" />
                <native:chip :label="__('app.weekday_abbreviations.7')" native:model="sunday" :a11y-label="__('app.weekday_names.7')" />
            </native:row>
        @endif
        <native:button ref="save-bedtime-reminder" variant="primary" size="lg" class="w-full" @tap="save"
                       :disabled="$awaitingExactPermission || $awaitingNotificationPermission">{{ __('app.save_alarm') }}</native:button>
    </native:column>
</native:scroll-view>
