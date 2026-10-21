@use('App\Application\Challenges\AnswerState')
@use('App\Application\PlayerProgress\PlayerProgress')
@use('App\Icons\Android')

<native:top-bar :title="__('app.challenge')" :subtitle="__('app.challenge_subtitle', ['required' => $requiredCorrectAnswers, 'total' => count($questions)])" display-mode="inline"/>

<native:scroll-view class="w-full h-full bg-theme-background">
<native:column ref="challenge-screen" class="w-full gap-4 p-5">
    @if (! $unavailable)
        @php($challengeTypeName = __('challenges.types.'.$challengeType.'.name'))
        @php($topic = $challengeType === 'trivia' ? $this->currentTopic : null)
        <native:row class="w-full items-center gap-2">
            @if ($topic !== null)
                <native:icon :android="$topic->icon()" class="text-theme-primary" size="18"/>
            @endif
            <native:text class="flex-1 text-sm text-theme-on-surface-variant">
                {{ $topic !== null
                    ? __('app.challenge_type_with_topic', ['type' => $challengeTypeName, 'topic' => $topic->label()])
                    : __('app.challenge_type_label', ['type' => $challengeTypeName]) }}
            </native:text>
            @if (! $completed && $comboCount >= 2)
                <native:row ref="combo-badge" key="combo-badge" class="items-center gap-1 rounded-full bg-theme-sunrise px-3 py-1"
                            :scale="1.08" animate-loop="true" :animate-duration="650" animate-easing="ease-in-out"
                            :a11y-label="__('app.challenge_combo', ['count' => $comboCount])">
                    <native:icon :android="Android::LocalFireDepartment" name="local_fire_department" class="text-theme-on-sunrise" size="16"/>
                    <native:text font="accent" class="text-sm text-theme-on-sunrise">{{ __('app.challenge_combo', ['count' => $comboCount]) }}</native:text>
                </native:row>
            @endif
        </native:row>
    @endif
    @if ($unavailable)
        <native:activity-indicator />
    @elseif (! $completed)
        @if ($this->memorizing)
            <native:column ref="memory-sequence" key="memory-{{ $questions[$questionIndex]['id'] }}" native:poll="1s"
                           class="w-full items-center gap-4 rounded-2xl border border-theme-outline bg-theme-surface p-5">
                <native:text font="accent" class="text-lg text-center text-theme-primary">{{ $questions[$questionIndex]['instruction'] }}</native:text>
                <native:text font="accent" class="text-4xl text-center text-theme-on-surface">{{ $questions[$questionIndex]['memory_sequence'] }}</native:text>
                @if ($memoryDeadline > 0)
                    <native:row class="items-center gap-2" :a11y-label="__('app.memory_seconds_left', ['seconds' => $this->memorySecondsLeft])">
                        <native:icon :android="Android::Timer" name="timer" class="text-theme-sunrise" size="24"/>
                        <native:text ref="memory-countdown" key="memory-countdown" font="accent" content-transition="numeric"
                                     class="text-2xl text-theme-sunrise">{{ $this->memorySecondsLeft }}</native:text>
                    </native:row>
                @endif
                <native:button ref="show-memory-question" variant="primary" class="w-full" size="lg" @tap="showMemoryQuestion"
                               :a11y-label="__('app.ready_to_answer')">{{ __('app.ready_to_answer') }}</native:button>
            </native:column>
        @else
            @php($question = $questions[$questionIndex])
            @php($answeredCorrectly = $this->answeredCorrectly)
            <native:column class="w-full gap-4 rounded-2xl border border-theme-outline bg-theme-sunrise/15 p-5">
                <native:row class="w-full items-center gap-2">
                    <native:text font="accent" class="flex-1 text-sm text-theme-sunrise">{{ __('app.question_of', ['current' => $questionIndex + 1, 'total' => count($questions)]) }}</native:text>
                    @if (isset($question['level']))
                        <native:row ref="question-level" class="items-center rounded-full bg-theme-primary/15 px-3 py-1">
                            <native:text class="text-xs text-theme-primary">{{ __('app.question_level', ['level' => __('app.'.$question['level'])]) }}</native:text>
                        </native:row>
                    @endif
                </native:row>
                <native:progress-bar :value="($questionIndex + ($answerRevealed ? 1 : 0)) / count($questions)"/>
                <native:text class="text-sm text-theme-on-surface-variant">{{ $question['instruction'] }}</native:text>
                <native:text font="accent" class="text-3xl leading-tight text-theme-on-surface">{{ $question['question'] }}</native:text>
            </native:column>

            <native:column class="w-full gap-3" :a11y-label="__('app.answer_options')">
                @foreach ($question['options'] as $option)
                    <native:answer-option
                        key="answer-{{ $question['id'] }}-{{ md5($option) }}"
                        :label="$option"
                        :index="$loop->index"
                        :state="AnswerState::for($answerRevealed, $option === $question['answer'], $selectedAnswerIndex === $loop->index)->value"
                        ref-prefix="answer"
                        @chosen="selectAnswer"
                    />
                @endforeach
            </native:column>

            @if ($answerRevealed)
                <native:column ref="answer-feedback" key="feedback-{{ $question['id'] }}"
                               class="w-full gap-2 rounded-2xl p-4 {{ $answeredCorrectly ? 'bg-theme-success/15' : 'bg-theme-destructive/15' }}">
                    <native:row class="w-full items-center gap-2">
                        <native:icon :android="$answeredCorrectly ? Android::Celebration : Android::Lightbulb"
                                     :name="$answeredCorrectly ? 'celebration' : 'lightbulb'"
                                     class="{{ $answeredCorrectly ? 'text-theme-success' : 'text-theme-destructive' }}" size="24"/>
                        <native:text font="accent" class="flex-1 text-lg text-theme-on-surface">
                            {{ $answeredCorrectly
                                ? $this->praise
                                : __('app.answer_wrong_title') }}
                        </native:text>
                    </native:row>
                    @unless ($answeredCorrectly)
                        <native:text class="text-base text-theme-on-surface">{{ __('app.correct_answer_was', ['answer' => $question['answer']]) }}</native:text>
                    @endunless
                    @if (filled($question['explanation'] ?? null))
                        <native:text class="text-sm text-theme-on-surface-variant">
                            {{ $challengeType === 'trivia' ? __('app.did_you_know', ['fact' => $question['explanation']]) : $question['explanation'] }}
                        </native:text>
                    @endif
                </native:column>
            @endif

            @php($finishesAttempt = $this->finishesAttempt)
            <native:button ref="continue-challenge" variant="primary" class="w-full" size="lg" @tap="continueChallenge"
                           :disabled="! $answerRevealed" :a11y-label="$finishesAttempt ? __('app.check_answers') : __('app.continue')">{{ $finishesAttempt ? __('app.check_answers') : __('app.continue') }}</native:button>
        @endif
        @if ($snoozeAvailable)
            <native:button ref="snooze-alarm" variant="secondary" class="w-full" size="lg" @tap="snoozeAlarm"
                           :a11y-label="__('app.snooze_for_minutes', ['minutes' => $this->snoozeMinutes])">{{ __('app.snooze_for_minutes', ['minutes' => $this->snoozeMinutes]) }}</native:button>
        @endif
    @elseif ($passed)
        <native:column ref="challenge-passed" class="w-full items-center gap-5 rounded-2xl bg-theme-success/15 p-6">
            <native:icon :android="Android::WbSunny" name="wb_sunny" class="text-theme-sunrise" size="72"
                         :scale="1.12" animate-loop="true" :animate-duration="900" animate-easing="ease-in-out"
                         :a11y-label="__('app.challenge_completed')"/>
            <native:text font="accent" class="text-3xl text-center text-theme-on-background">{{ __('app.challenge_completed') }}</native:text>
            <native:text class="text-lg text-center text-theme-on-surface-variant">{{ __('app.challenge_score', ['correct' => $correctAnswers, 'total' => count($questions)]) }}</native:text>
            @if ($bestCombo >= 2)
                <native:row class="items-center gap-2 rounded-full bg-theme-sunrise px-4 py-2">
                    <native:icon :android="Android::LocalFireDepartment" name="local_fire_department" class="text-theme-on-sunrise" size="18"/>
                    <native:text font="accent" class="text-sm text-theme-on-sunrise">{{ __('app.challenge_best_combo', ['count' => $bestCombo]) }}</native:text>
                </native:row>
            @endif
            @if ($alarmStopped && $earnedXp > 0)
                <native:row ref="earned-xp" class="items-center gap-2 rounded-full bg-theme-primary px-4 py-2"
                            :scale="1.06" animate-loop="true" :animate-duration="800" animate-easing="ease-in-out">
                    <native:icon :android="Android::Star" name="star" class="text-theme-on-primary" size="18"/>
                    <native:text font="accent" class="text-base text-theme-on-primary">{{ __('progress.xp_gained', ['xp' => $earnedXp]) }}</native:text>
                </native:row>
            @endif
            @if ($reachedLevel !== null)
                <native:text ref="level-up" font="accent" class="text-lg text-center text-theme-sunrise">
                    {{ __('progress.level_up', ['level' => $reachedLevel, 'title' => $this->reachedLevelTitle]) }}
                </native:text>
            @endif
            @foreach ($this->newAchievements as $achievement)
                <native:row ref="unlocked-{{ $achievement->value }}" key="unlocked-{{ $achievement->value }}"
                            class="w-full items-center gap-3 rounded-xl border border-theme-sunrise bg-theme-surface p-3"
                            :a11y-label="__('progress.achievement_unlocked').': '.$achievement->title()">
                    <native:icon :android="$achievement->icon()" class="text-theme-sunrise" size="28"/>
                    <native:column class="flex-1 gap-1">
                        <native:text class="text-xs text-theme-on-surface-variant">{{ __('progress.achievement_unlocked') }}</native:text>
                        <native:text font="accent" class="text-base text-theme-on-surface">{{ $achievement->title() }}</native:text>
                    </native:column>
                </native:row>
            @endforeach
            <native:text class="text-base text-center text-theme-on-surface-variant">{{ __('app.complete_alarm') }}</native:text>
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
        <native:column ref="challenge-failed" class="w-full items-center gap-5 rounded-2xl bg-theme-warning/15 p-6">
            <native:icon :android="Android::Bolt" name="bolt" class="text-theme-warning" size="56" :a11y-label="__('app.try_again')"/>
            <native:text font="accent" class="text-3xl text-center text-theme-on-background">{{ $correctAnswers }} / {{ $answeredCount ?: count($questions) }}
                {{ __('app.correct') }}
            </native:text>
            <native:text
                class="text-lg text-center text-theme-on-surface-variant">{{ __('app.retry_challenge', ['required' => $requiredCorrectAnswers, 'total' => count($questions)]) }}</native:text>
            @if ($this->endedEarly)
                <native:text ref="ended-early" class="text-sm text-center text-theme-on-surface-variant">{{ __('app.challenge_ended_early', ['required' => $requiredCorrectAnswers]) }}</native:text>
            @endif
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
                        <native:text ref="routine-xp" font="accent" class="text-base text-theme-primary">{{ __('progress.xp_gained', ['xp' => PlayerProgress::RoutineXp]) }}</native:text>
                    </native:column>
                @endif

                <native:button ref="return-home-from-routine" class="w-full" size="lg" variant="primary" @tap="returnHome"
                               :a11y-label="__('app.return_home')">{{ __('app.return_home') }}</native:button>
            </native:column>
        </native:scroll-view>
    </native:bottom-sheet>
@endif
</native:column>
</native:scroll-view>
