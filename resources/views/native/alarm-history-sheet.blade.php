<native:bottom-sheet :visible="$historyOpen" detents="medium,large" @dismiss="closeHistory"
                     :a11y-label="__('app.alarm_history')">
    <native:scroll-view class="w-full bg-theme-surface">
        <native:column class="w-full gap-3 p-6">
            <native:row class="w-full items-center gap-3">
                <native:column class="flex-1 gap-1">
                    <native:text font="accent" class="text-2xl text-theme-on-surface">{{ __('app.alarm_history') }}</native:text>
                </native:column>
            </native:row>

            @foreach ($executions as $execution)
                <native:row key="execution-{{ $execution->id }}"
                            class="w-full items-center gap-3 rounded-xl border border-theme-outline bg-theme-surface-variant p-4"
                            :a11y-label="$execution->alarm_label.' · '.$execution->displayTimestamp().' · '.$execution->displayStatus()">
                    <native:icon name="history" class="text-theme-secondary" size="20"/>
                    <native:column class="flex-1 gap-1">
                        <native:text class="text-base text-theme-on-surface">{{ $execution->alarm_label }}</native:text>
                        <native:text class="text-sm text-theme-on-surface-variant">
                            {{ $execution->displayTimestamp() }} · {{ $execution->displayStatus() }}
                        </native:text>
                    </native:column>
                </native:row>
            @endforeach
        </native:column>
    </native:scroll-view>
</native:bottom-sheet>
