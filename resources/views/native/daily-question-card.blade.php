@use('App\Application\Challenges\AnswerState')
@use('App\Application\PlayerProgress\PlayerProgress')
@use('App\Icons\Android')

@php($dailyQuestion = $this->dailyQuestion)
@php($question = $this->question)
<native:column ref="daily-question" class="w-full gap-3 rounded-2xl border border-theme-outline bg-theme-surface p-5">
    <native:row class="w-full items-center gap-2">
        <native:icon :android="Android::Quiz" name="quiz" class="text-theme-primary" size="22"/>
        <native:text font="accent" class="flex-1 text-base text-theme-on-surface">{{ __('progress.daily_question.title') }}</native:text>
        @unless ($dailyQuestion->isAnswered())
            <native:row class="items-center rounded-full bg-theme-primary/15 px-3 py-1">
                <native:text class="text-xs text-theme-primary">{{ __('progress.xp_gained', ['xp' => PlayerProgress::DailyQuestionXp]) }}</native:text>
            </native:row>
        @endunless
    </native:row>
    <native:text font="accent" class="text-xl text-theme-on-surface">{{ $question['question'] }}</native:text>

    @foreach ($question['options'] as $option)
        <native:answer-option
            key="daily-{{ $dailyQuestion->date }}-{{ md5($option) }}"
            :label="$option"
            :index="$loop->index"
            :state="AnswerState::for($dailyQuestion->isAnswered(), $option === $question['answer'], $question['selected_index'] === $loop->index)->value"
            ref-prefix="daily-answer"
            @chosen="answer"
        />
    @endforeach

    @if ($dailyQuestion->isAnswered())
        <native:column ref="daily-question-result" class="w-full gap-1 rounded-xl p-3 {{ $dailyQuestion->correct ? 'bg-theme-success/15' : 'bg-theme-destructive/15' }}">
            <native:text font="accent" class="text-base text-theme-on-surface">
                {{ $dailyQuestion->correct
                    ? __('progress.daily_question.correct', ['xp' => PlayerProgress::DailyQuestionXp])
                    : __('progress.daily_question.wrong', ['answer' => $question['answer']]) }}
            </native:text>
            @if (filled($question['explanation']))
                <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.did_you_know', ['fact' => $question['explanation']]) }}</native:text>
            @endif
            <native:text class="text-sm text-theme-on-surface-variant">{{ __('progress.daily_question.come_back') }}</native:text>
        </native:column>
        @foreach ($this->newAchievements as $achievement)
            <native:row ref="daily-unlocked-{{ $achievement->value }}" key="daily-unlocked-{{ $achievement->value }}"
                        class="w-full items-center gap-3 rounded-xl border border-theme-sunrise bg-theme-surface p-3"
                        :a11y-label="__('progress.achievement_unlocked').': '.$achievement->title()">
                <native:icon :android="$achievement->icon()" class="text-theme-sunrise" size="28"/>
                <native:column class="flex-1 gap-1">
                    <native:text class="text-xs text-theme-on-surface-variant">{{ __('progress.achievement_unlocked') }}</native:text>
                    <native:text font="accent" class="text-base text-theme-on-surface">{{ $achievement->title() }}</native:text>
                </native:column>
            </native:row>
        @endforeach
    @else
        <native:text class="text-sm text-theme-on-surface-variant">{{ __('progress.daily_question.reward', ['xp' => PlayerProgress::DailyQuestionXp]) }}</native:text>
    @endif
</native:column>
