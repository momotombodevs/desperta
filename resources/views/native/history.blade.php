<native:top-bar :title="__('app.alarm_history')" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-surface">
    <native:column class="w-full gap-3 p-6">
        @if ($this->executions->isEmpty())
            <native:column class="w-full items-center gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-8">
                <native:icon name="history" class="text-theme-secondary" size="40" :a11y-label="__('app.alarm_history_empty')" />
                <native:text font="accent" class="text-lg text-center text-theme-on-surface">{{ __('app.alarm_history_empty_title') }}</native:text>
                <native:text class="text-base text-center text-theme-on-surface-variant">{{ __('app.alarm_history_empty') }}</native:text>
            </native:column>
        @else
            @foreach ($this->executions as $execution)
                <native:row key="execution-{{ $execution->id }}"
                            class="w-full items-center gap-3 rounded-xl border border-theme-outline bg-theme-surface-variant p-4"
                            :a11y-label="$execution->alarm_label.' · '.$execution->displayTimestamp().' · '.$execution->displayStatus()">
                    <native:icon name="history" class="text-theme-secondary" size="20" />
                    <native:column class="flex-1 gap-1">
                        <native:text class="text-base text-theme-on-surface">{{ $execution->alarm_label }}</native:text>
                        <native:text class="text-sm text-theme-on-surface-variant">
                            {{ $execution->displayTimestamp() }} · {{ $execution->displayStatus() }}
                        </native:text>
                    </native:column>
                </native:row>
            @endforeach
        @endif
    </native:column>
</native:scroll-view>
