@use('App\Icons\Android')

<native:top-bar :title="__('app.challenge')" :subtitle="__('app.challenge_subtitle', ['required' => $requiredCorrectAnswers, 'total' => count($questions)])" display-mode="inline"/>

<native:column ref="challenge-screen" class="w-full h-full gap-4 bg-theme-background p-5">
    @if ($unavailable)
        <native:activity-indicator />
    @elseif (! $completed)
        @if ($challengeType === 'memory' && $memoryPhase === 'memorize')
            <native:column class="w-full items-center gap-4 rounded-2xl border border-theme-outline bg-theme-surface p-5">
                <native:text font="accent" class="text-lg text-center text-theme-primary">{{ $questions[$questionIndex]['instruction'] }}</native:text>
                <native:text class="text-3xl text-center text-theme-on-surface">{{ $questions[$questionIndex]['memory_sequence'] }}</native:text>
                <native:button ref="show-memory-question" variant="primary" class="w-full" size="lg" @tap="showMemoryQuestion"
                               :a11y-label="__('app.ready_to_answer')">{{ __('app.ready_to_answer') }}</native:button>
            </native:column>
        @else
            <native:column class="w-full gap-4 rounded-2xl border border-theme-outline bg-theme-sunrise/15 p-5">
            <native:text font="accent" class="text-sm text-theme-sunrise">{{ __('app.question_of', ['current' => $questionIndex + 1, 'total' => count($questions)]) }}</native:text>
            <native:progress-bar :value="($questionIndex + 1) / count($questions)"/>
            <native:text class="text-sm text-theme-on-surface-variant">{{ $questions[$questionIndex]['instruction'] }}</native:text>
            <native:text font="accent"
                         class="text-3xl leading-tight text-theme-on-surface">{{ $questions[$questionIndex]['question'] }}</native:text>
        </native:column>

        <native:column class="w-full gap-3" :a11y-label="__('app.answer_options')">
            @foreach ($questions[$questionIndex]['options'] as $option)
                <native:pressable
                    ref="answer-{{ $loop->index }}"
                    key="question-{{ $questions[$questionIndex]['id'] }}-answer-{{ $loop->index }}"
                    class="w-full flex-row items-center gap-3 rounded-xl border p-4 {{ $selectedAnswerIndex === $loop->index ? 'border-theme-primary bg-theme-primary/15' : 'border-theme-outline bg-theme-surface' }}"
                    @tap="selectAnswer({{ $loop->index }})"
                    :a11y-label="$option"
                >
                    @if ($selectedAnswerIndex === $loop->index)
                        <native:icon name="check" class="text-theme-primary" size="20" a11y-label="{{ __('app.selected_answer') }}"/>
                    @else
                        <native:icon name="circle" class="text-theme-on-surface-variant" size="20"/>
                    @endif
                    <native:text class="flex-1 text-lg text-theme-on-surface">{{ $option }}</native:text>
                </native:pressable>
            @endforeach
        </native:column>

        <native:button ref="continue-challenge" variant="primary" class="w-full" size="lg" @tap="continueChallenge"
                       :disabled="$selectedAnswerIndex === null" :a11y-label="$questionIndex === count($questions) - 1 ? __('app.check_answers') : __('app.continue')">{{ $questionIndex === count($questions) - 1 ? __('app.check_answers') : __('app.continue') }}</native:button>
        @endif
        @if ($snoozeAvailable)
            <native:button ref="snooze-alarm" variant="secondary" class="w-full" size="lg" @tap="snoozeAlarm"
                           :a11y-label="__('app.snooze_for_minutes', ['minutes' => $this->snoozeMinutes])">{{ __('app.snooze_for_minutes', ['minutes' => $this->snoozeMinutes]) }}</native:button>
        @endif
    @elseif ($passed)
        <native:column class="w-full items-center gap-5 rounded-2xl bg-theme-success/15 p-6">
            <native:text font="accent" class="text-3xl text-center text-theme-on-background">{{ __('app.challenge_completed') }}</native:text>
            <native:text class="text-lg text-center text-theme-on-surface-variant">{{ __('app.complete_alarm') }}</native:text>
            @if (! $alarmStopped)
                <native:button ref="turn-off-alarm" class="w-full" size="lg" variant="primary" @tap="turnOffAlarm" :a11y-label="__('app.finish_alarm')">{{ __('app.finish_alarm') }}</native:button>
            @else
                @if ($this->routineSteps->isNotEmpty())
                    <native:button ref="open-morning-routine" class="w-full" size="lg" variant="secondary" @tap="openMorningRoutine"
                                   :a11y-label="__('app.view_morning_routine')">{{ __('app.view_morning_routine') }}</native:button>
                @endif
                <native:button ref="return-home"  class="w-full" size="lg" variant="primary" @tap="returnHome" :a11y-label="__('app.return_home')">{{ __('app.return_home') }}</native:button>
            @endif
        </native:column>
    @else
        <native:column class="w-full items-center gap-5 rounded-2xl bg-theme-warning/15 p-6">
            <native:text font="accent" class="text-3xl text-center text-theme-on-background">{{ $correctAnswers }} / {{ count($questions) }}
                {{ __('app.correct') }}
            </native:text>
            <native:text
                class="text-lg text-center text-theme-on-surface-variant">{{ __('app.retry_challenge', ['required' => $requiredCorrectAnswers, 'total' => count($questions)]) }}</native:text>
            @if ($showRetryHint)
                <native:text class="text-sm text-center text-theme-on-surface-variant">{{ __('app.challenge_adaptive_hint') }}</native:text>
            @endif
            <native:button ref="retry-challenge" variant="primary" class="w-full" size="lg" :a11y-label="__('app.try_again')"
                           @tap="retry">{{ __('app.try_again') }}</native:button>
        </native:column>
    @endif

@if ($alarmStopped && $this->routineSteps->isNotEmpty())
    <native:bottom-sheet :visible="$routineSheetVisible" detents="medium,large" @dismiss="dismissRoutineSheet"
                         :a11y-label="__('app.morning_routine_title')">
        <native:scroll-view class="w-full bg-theme-background">
            <native:column class="w-full gap-3 p-5">
                <native:column class="w-full gap-1 rounded-2xl bg-theme-sunrise p-5">
                    <native:text font="accent" class="text-xl text-theme-on-sunrise">{{ __('app.morning_routine_title') }}</native:text>
                    <native:text class="text-sm text-theme-on-sunrise">{{ __('app.morning_routine_progress', ['completed' => $this->routineSteps->whereNotNull('completed_at')->count(), 'total' => $this->routineSteps->count()]) }}</native:text>
                </native:column>

                @foreach ($this->routineSteps as $step)
                    <native:pressable ref="routine-step-{{ $step->id }}" key="routine-step-{{ $step->id }}"
                                      class="w-full rounded-xl border border-theme-outline bg-theme-surface p-4"
                                      @tap="completeRoutineStep('{{ $step->id }}')"
                                      :a11y-label="$step->completed_at ? __('app.morning_routine_step_completed', ['step' => $step->label]) : __('app.morning_routine_mark_complete', ['step' => $step->label])">
                        <native:row class="w-full items-center gap-3">
                            <native:icon :name="$step->completed_at ? 'check-circle' : 'circle'" size="24"
                                         class="{{ $step->completed_at ? 'text-theme-success' : 'text-theme-outline' }}" />
                            <native:text class="flex-1 text-base text-theme-on-surface">{{ $step->label }}</native:text>
                        </native:row>
                    </native:pressable>
                @endforeach

                @if ($this->routineSteps->every(fn ($step) => $step->completed_at !== null))
                    <native:column class="w-full items-center rounded-2xl bg-theme-success/15 p-4">
                        <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.morning_routine_complete') }}</native:text>
                    </native:column>
                @endif

                <native:button ref="return-home-from-routine" class="w-full" size="lg" variant="primary" @tap="returnHome"
                               :a11y-label="__('app.return_home')">{{ __('app.return_home') }}</native:button>
            </native:column>
        </native:scroll-view>
    </native:bottom-sheet>
@endif
</native:column>
