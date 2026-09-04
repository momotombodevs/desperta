@use('App\Icons\Android')

<native:top-bar :title="__('app.challenge')" :subtitle="__('app.challenge_subtitle')" display-mode="inline"/>

<native:column ref="challenge-screen" class="w-full h-full gap-5 bg-theme-background p-6">
    @if (! $completed)
        <native:column class="w-full gap-4 rounded-2xl border border-theme-outline bg-theme-surface p-5">
            <native:text font="accent" class="text-sm text-theme-primary">{{ __('app.question_of', ['current' => $questionIndex + 1, 'total' => count($questions)]) }}</native:text>
            <native:progress-bar :value="($questionIndex + 1) / count($questions)"/>
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
        @if ($snoozeAvailable)
            <native:button ref="snooze-alarm" variant="secondary" class="w-full" size="lg" @tap="snoozeAlarm"
                           :a11y-label="__('app.snooze_for_minutes', ['minutes' => 5])">{{ __('app.snooze_for_minutes', ['minutes' => 5]) }}</native:button>
        @endif
    @elseif ($passed)
        <native:column class="w-full items-center gap-5 rounded-2xl bg-theme-success/15 p-6">
            <native:text font="accent" class="text-3xl text-center text-theme-on-background">{{ __('app.challenge_completed') }}</native:text>
            <native:text class="text-lg text-center text-theme-on-surface-variant">{{ __('app.complete_alarm') }}</native:text>
            @if (! $alarmStopped)
                <native:button ref="turn-off-alarm" class="w-full" size="lg" variant="primary" @tap="turnOffAlarm" :a11y-label="__('app.finish_alarm')">{{ __('app.finish_alarm') }}</native:button>
            @else
                <native:button ref="return-home"  class="w-full" size="lg" variant="primary" @tap="returnHome" :a11y-label="__('app.return_home')">{{ __('app.return_home') }}</native:button>
            @endif
        </native:column>
    @else
        <native:column class="w-full items-center gap-5 rounded-2xl bg-theme-warning/15 p-6">
            <native:text font="accent" class="text-3xl text-center text-theme-on-background">{{ $correctAnswers }} / {{ count($questions) }}
                {{ __('app.correct') }}
            </native:text>
            <native:text
                class="text-lg text-center text-theme-on-surface-variant">{{ __('app.retry_challenge') }}</native:text>
            <native:button ref="retry-challenge" variant="primary" class="w-full" size="lg" :a11y-label="__('app.try_again')"
                           @tap="retry">{{ __('app.try_again') }}</native:button>
        </native:column>
    @endif
</native:column>
