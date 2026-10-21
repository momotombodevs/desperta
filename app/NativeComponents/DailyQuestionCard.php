<?php

namespace App\NativeComponents;

use App\Application\PlayerProgress\Achievement;
use App\Application\PlayerProgress\DailyQuestionPicker;
use App\Application\PlayerProgress\PlayerProgress;
use App\Models\DailyQuestion;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Device;

final class DailyQuestionCard extends NativeComponent
{
    /** @var list<string> Achievement values unlocked by answering today's question. */
    public array $unlockedAchievements = [];

    #[Computed]
    public function dailyQuestion(): DailyQuestion
    {
        return app(DailyQuestionPicker::class)->today();
    }

    /** @return array{id: string, question: string, options: list<string>, answer: string, explanation: ?string, selected_index: ?int} */
    #[Computed]
    public function question(): array
    {
        return app(DailyQuestionPicker::class)->presented($this->dailyQuestion);
    }

    /** @return list<Achievement> */
    #[Computed]
    public function newAchievements(): array
    {
        return array_values(array_filter(array_map(fn (string $value): ?Achievement => Achievement::tryFrom($value), $this->unlockedAchievements)));
    }

    public function answer(int $optionIndex): void
    {
        $picker = app(DailyQuestionPicker::class);

        if ($picker->today()->isAnswered()) {
            return;
        }

        $dailyQuestion = $picker->answer($optionIndex);

        if ($dailyQuestion->correct === false) {
            Device::vibrate();
        }

        $this->unlockedAchievements = array_map(
            fn (Achievement $achievement): string => $achievement->value,
            app(PlayerProgress::class)->announceNewAchievements(),
        );
    }

    public function render(): View
    {
        return view('native.daily-question-card');
    }
}
