<?php

namespace App\NativeComponents;

use App\Application\PlayerProgress\DailyQuestionPicker;
use App\Application\PlayerProgress\PlayerProgress;
use App\Models\DailyQuestion;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Device;

final class DailyQuestionCard extends NativeComponent
{
    #[Computed]
    public function dailyQuestion(): DailyQuestion
    {
        return app(DailyQuestionPicker::class)->today();
    }

    public function answer(int $optionIndex): void
    {
        $dailyQuestion = app(DailyQuestionPicker::class)->answer($optionIndex);

        if ($dailyQuestion->correct === false) {
            Device::vibrate();
        }

        $this->emit('daily-question-answered');
    }

    public function rewardXp(): int
    {
        return PlayerProgress::DailyQuestionXp;
    }

    public function render(): View
    {
        return view('native.daily-question-card');
    }
}
