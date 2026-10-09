<native:top-bar :title="$this->navTitle()" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-5">
        @if ($executionId !== '')
            @if ($this->executionSteps->isNotEmpty())
                <native:column class="w-full gap-2 rounded-2xl bg-theme-sunrise p-5">
                    <native:text font="accent" class="text-xl text-theme-on-sunrise">{{ __('app.morning_routine_title') }}</native:text>
                    <native:text class="text-sm text-theme-on-sunrise">{{ __('app.morning_routine_progress', ['completed' => $this->executionSteps->whereNotNull('completed_at')->count(), 'total' => $this->executionSteps->count()]) }}</native:text>
                </native:column>

                @foreach ($this->executionSteps as $step)
                    <native:pressable ref="routine-complete-{{ $step->id }}" class="w-full rounded-xl border border-theme-outline bg-theme-surface p-4"
                                      @tap="completeRoutineStep('{{ $step->id }}')"
                                      :a11y-label="$step->completed_at ? __('app.morning_routine_step_completed', ['step' => $step->label]) : __('app.morning_routine_mark_complete', ['step' => $step->label])">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :name="$step->completed_at ? 'check-circle' : 'circle'" size="24"
                                         class="{{ $step->completed_at ? 'text-theme-success' : 'text-theme-outline' }}" />
                            <native:text class="flex-1 text-base text-theme-on-surface">{{ $step->label }}</native:text>
                        </native:row>
                    </native:pressable>
                @endforeach

                @if ($this->routineStatus === 'complete')
                    <native:column class="w-full items-center rounded-2xl bg-theme-success/15 p-5">
                        <native:text font="accent" class="text-lg text-theme-on-surface">{{ __('app.morning_routine_complete') }}</native:text>
                    </native:column>
                @endif
            @elseif ($this->configuredSteps->isEmpty())
                <native:column class="w-full items-center gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-6">
                    <native:text font="accent" class="text-lg text-center text-theme-on-surface">{{ __('app.morning_routine_not_configured') }}</native:text>
                    <native:text class="text-sm text-center text-theme-on-surface-variant">{{ __('app.morning_routine_optional') }}</native:text>
                </native:column>
            @else
                <native:column class="w-full gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-5">
                    <native:text class="text-base text-theme-on-surface">{{ __('app.morning_routine_ready') }}</native:text>
                    <native:button ref="start-morning-routine" :label="__('app.start_morning_routine')" variant="primary" @tap="startRoutine" />
                </native:column>
            @endif
        @else
            <native:column class="w-full gap-2">
                <native:text font="accent" class="text-xl text-theme-on-background">{{ __('app.morning_routine_title') }}</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.morning_routine_setup_description') }}</native:text>
            </native:column>

            <native:row class="w-full items-center gap-2">
                <native:outlined-text-input class="flex-1" :label="__('app.morning_routine_step_label')" native:model.live="stepLabel"
                                            :a11y-label="__('app.morning_routine_step_label')" />
                <native:button ref="add-routine-step" :label="__('app.add_morning_routine_step')" @tap="addStep" />
            </native:row>
            @if ($validationMessage !== '')
                <native:text class="text-sm text-theme-destructive">{{ $validationMessage }}</native:text>
            @endif

            @forelse ($this->configuredSteps as $index => $step)
                <native:column class="w-full gap-3 rounded-xl border border-theme-outline bg-theme-surface p-4">
                    @if ($editingStepId === $step->id)
                        <native:outlined-text-input :label="__('app.morning_routine_step_label')" native:model.live="editingLabel"
                                                    :a11y-label="__('app.morning_routine_step_label')" />
                        @if ($validationMessage !== '')
                            <native:text class="text-sm text-theme-destructive">{{ $validationMessage }}</native:text>
                        @endif
                        <native:row class="w-full gap-2">
                            <native:button ref="save-routine-step-{{ $step->id }}" :label="__('app.save')" @tap="saveStep" />
                            <native:button :label="__('app.cancel')" variant="secondary" @tap="cancelEditing" />
                        </native:row>
                    @else
                        <native:text class="text-base text-theme-on-surface">{{ $step->label }}</native:text>
                        <native:row class="w-full gap-2">
                            <native:button :label="__('app.move_up')" :disabled="$index === 0" @tap="moveStepUp('{{ $step->id }}')" />
                            <native:button :label="__('app.move_down')" :disabled="$index === $this->configuredSteps->count() - 1" @tap="moveStepDown('{{ $step->id }}')" />
                        </native:row>
                        <native:row class="w-full gap-2">
                            <native:button :label="__('app.edit')" variant="secondary" @tap="editStep('{{ $step->id }}')" />
                            <native:button :label="__('app.delete')" variant="secondary" @tap="deleteStep('{{ $step->id }}')" />
                        </native:row>
                    @endif
                </native:column>
            @empty
                <native:column class="w-full items-center gap-2 rounded-2xl border border-theme-outline bg-theme-surface p-6">
                    <native:text class="text-base text-center text-theme-on-surface">{{ __('app.morning_routine_empty') }}</native:text>
                    <native:text class="text-sm text-center text-theme-on-surface-variant">{{ __('app.morning_routine_optional') }}</native:text>
                </native:column>
            @endforelse
        @endif
    </native:column>
</native:scroll-view>
