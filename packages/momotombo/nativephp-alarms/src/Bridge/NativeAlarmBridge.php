<?php

namespace Momotombo\NativePHPAlarms\Bridge;

/**
 * Replaceable boundary for the NativePHP alarm bridge.
 *
 * Application and package tests can provide a deterministic implementation
 * without loading Android bridge functions.
 */
interface NativeAlarmBridge
{
    /**
     * Invoke one manifest-declared native endpoint.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function call(string $method, array $parameters = []): array;
}
