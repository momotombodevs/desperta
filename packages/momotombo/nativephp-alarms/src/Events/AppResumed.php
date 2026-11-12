<?php

namespace Momotombo\NativePHPAlarms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Native\Mobile\Events\Contracts\DispatchableFromNative;

/**
 * Dispatched to the active native screen when Android resumes the app.
 */
final readonly class AppResumed implements DispatchableFromNative
{
    use Dispatchable;
}
