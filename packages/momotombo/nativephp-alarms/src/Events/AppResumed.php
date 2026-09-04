<?php

namespace Momotombo\NativePHPAlarms\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched to the active native screen when Android resumes the app.
 */
final readonly class AppResumed
{
    use Dispatchable;
}
