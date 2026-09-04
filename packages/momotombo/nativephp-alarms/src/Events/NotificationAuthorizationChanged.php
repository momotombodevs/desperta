<?php

namespace Momotombo\NativePHPAlarms\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched by Android when a requested notification-permission flow resolves.
 *
 * `$requestId` is supplied by the caller of `requestNotificationAuthorization()`
 * and lets UI state correlate the asynchronous platform result.
 */
final readonly class NotificationAuthorizationChanged
{
    use Dispatchable;

    public function __construct(
        public bool $granted,
        public string $requestId,
    ) {}
}
