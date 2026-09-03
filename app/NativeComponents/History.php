<?php

namespace App\NativeComponents;

use App\Models\AlarmExecution;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

final class History extends NativeComponent
{
    /** @return Collection<int, AlarmExecution> */
    #[Computed]
    public function executions(): Collection
    {
        return AlarmExecution::query()
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        return view('native.history');
    }
}
