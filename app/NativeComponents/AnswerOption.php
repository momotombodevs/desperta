<?php

namespace App\NativeComponents;

use App\Application\Challenges\AnswerState;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

/** One tappable answer of a trivia-style question; emits `chosen` with its index. */
final class AnswerOption extends NativeComponent
{
    public string $label = '';

    public int $index = 0;

    public string $state = 'idle';

    public string $refPrefix = 'answer';

    #[Computed]
    public function answerState(): AnswerState
    {
        return AnswerState::tryFrom($this->state) ?? AnswerState::Idle;
    }

    public function choose(): void
    {
        $this->emit('chosen', $this->index);
    }

    public function render(): View
    {
        return view('native.answer-option');
    }
}
