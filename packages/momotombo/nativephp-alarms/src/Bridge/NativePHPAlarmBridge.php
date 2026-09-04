<?php

namespace Momotombo\NativePHPAlarms\Bridge;

use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;

final class NativePHPAlarmBridge implements NativeAlarmBridge
{
    public function call(string $method, array $parameters = []): array
    {
        if (! function_exists('nativephp_call')) {
            throw new NativeAlarmSchedulingFailed('The NativePHP bridge is unavailable.');
        }

        $response = nativephp_call($method, json_encode($parameters, JSON_THROW_ON_ERROR));

        if ($response === null || $response === '') {
            throw new NativeAlarmSchedulingFailed("Native alarm call [{$method}] did not return a response.");
        }

        $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new NativeAlarmSchedulingFailed("Native alarm call [{$method}] returned an invalid response.");
        }

        return $decoded;
    }
}
