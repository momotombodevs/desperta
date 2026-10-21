@use('App\Icons\Android')

@php($dailyQuestion = $this->dailyQuestion)
@php($question = $dailyQuestion->question)
<native:column ref="daily-question" class="w-full gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-5">
    <native:row class="w-full items-center gap-2">
        <native:icon :android="Android::Quiz" name="quiz" class="text-theme-primary" size="22"/>
        <native:text font="accent" class="flex-1 text-base text-theme-on-surface">{{ __('progress.daily_question.title') }}</native:text>
        @unless ($dailyQuestion->isAnswered())
            <native:row class="items-center rounded-full bg-theme-primary/15 px-3 py-1">
                <native:text class="text-xs text-theme-primary">{{ __('progress.xp_gained', ['xp' => $this->rewardXp()]) }}</native:text>
            </native:row>
        @endunless
    </native:row>
    <native:text font="accent" class="text-xl text-theme-on-surface">{{ $question['question'] }}</native:text>

    @foreach ($question['options'] as $option)
        @php($optionState = ! $dailyQuestion->isAnswered() ? 'idle'
            : ($option === $question['answer'] ? 'correct' : ($option === $dailyQuestion->selected_option ? 'wrong' : 'dimmed')))
        <native:pressable ref="daily-answer-{{ $loop->index }}" key="daily-{{ $dailyQuestion->date }}-{{ $loop->index }}"
                          class="w-full flex-row items-center gap-3 rounded-xl border p-3 {{ match ($optionState) {
                              'correct' => 'border-theme-success bg-theme-success/15',
                              'wrong' => 'border-theme-destructive bg-theme-destructive/15',
                              default => 'border-theme-outline bg-theme-background',
                          } }}"
                          :opacity="$optionState === 'dimmed' ? 0.45 : 1"
                          :scale="$optionState === 'correct' ? 1.02 : 1"
                          :animate-duration="220" animate-easing="ease-out"
                          :press-scale="$dailyQuestion->isAnswered() ? 1 : 0.96"
                          @tap="answer({{ $loop->index }})" :a11y-label="$option">
            @if ($optionState === 'correct')
                <native:icon :android="Android::CheckCircle" name="check_circle" class="text-theme-success" size="20" :a11y-label="__('app.correct_answer')"/>
            @elseif ($optionState === 'wrong')
                <native:icon :android="Android::Cancel" name="cancel" class="text-theme-destructive" size="20" :a11y-label="__('app.wrong_answer')"/>
            @else
                <native:icon name="circle" class="text-theme-on-surface-variant" size="20"/>
            @endif
            <native:text class="flex-1 text-base text-theme-on-surface">{{ $option }}</native:text>
        </native:pressable>
    @endforeach

    @if ($dailyQuestion->isAnswered())
        <native:column ref="daily-question-result" class="w-full gap-1 rounded-xl p-3 {{ $dailyQuestion->correct ? 'bg-theme-success/15' : 'bg-theme-destructive/15' }}">
            <native:text font="accent" class="text-base text-theme-on-surface">
                {{ $dailyQuestion->correct
                    ? __('progress.daily_question.correct', ['xp' => $this->rewardXp()])
                    : __('progress.daily_question.wrong', ['answer' => $question['answer']]) }}
            </native:text>
            @if (filled($question['explanation'] ?? null))
                <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.did_you_know', ['fact' => $question['explanation']]) }}</native:text>
            @endif
            <native:text class="text-sm text-theme-on-surface-variant">{{ __('progress.daily_question.come_back') }}</native:text>
        </native:column>
    @else
        <native:text class="text-sm text-theme-on-surface-variant">{{ __('progress.daily_question.reward', ['xp' => $this->rewardXp()]) }}</native:text>
    @endif
</native:column>
