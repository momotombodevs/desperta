<?php

namespace App\NativeComponents;

use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\Preferences\AppPreferences;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

final class QuestionOfTheDay extends NativeComponent
{
    use ResumesActiveAlarm;

    public function mount(): void
    {
        app(AppPreferences::class)->applyLanguage();
    }

    public function render(): View
    {
        return view('native.question-of-the-day');
    }
}
