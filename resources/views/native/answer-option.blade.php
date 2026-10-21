@use('App\Application\Challenges\AnswerState')
@use('App\Icons\Android')

<native:pressable
    ref="{{ $refPrefix }}-{{ $index }}"
    class="w-full flex-row items-center gap-3 rounded-xl border p-4 {{ $this->answerState->containerClasses() }}"
    :opacity="$this->answerState->opacity()"
    :scale="$this->answerState->scale()"
    :animate-duration="220"
    animate-easing="ease-out"
    :press-scale="$this->answerState->isPressable() ? 0.96 : 1"
    @tap="choose"
    :a11y-label="$label"
>
    @switch ($this->answerState)
        @case (AnswerState::Correct)
            <native:icon :android="Android::CheckCircle" name="check_circle" class="text-theme-success" size="22" :a11y-label="__('app.correct_answer')"/>
            @break
        @case (AnswerState::Wrong)
            <native:icon :android="Android::Cancel" name="cancel" class="text-theme-destructive" size="22" :a11y-label="__('app.wrong_answer')"/>
            @break
        @case (AnswerState::Selected)
            <native:icon name="check" class="text-theme-primary" size="20" :a11y-label="__('app.selected_answer')"/>
            @break
        @default
            <native:icon name="circle" class="text-theme-on-surface-variant" size="20"/>
    @endswitch
    <native:text class="flex-1 text-lg text-theme-on-surface">{{ $label }}</native:text>
</native:pressable>
