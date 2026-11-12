<?php

namespace Momotombo\NativePHPAlarms\Bridge;

use JsonException;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;

/** Production bridge implementation backed by NativePHP's `nativephp_call()`. */
final class NativePHPAlarmBridge implements NativeAlarmBridge
{
    public function call(string $method, array $parameters = []): array
    {
        if (! function_exists('nativephp_call')) {
            throw new NativeAlarmSchedulingFailed('The NativePHP bridge is unavailable.');
        }

        try {
            $response = nativephp_call($method, json_encode((object) $parameters, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new NativeAlarmSchedulingFailed("Native alarm call [{$method}] has parameters that cannot be encoded.", previous: $exception);
        }

        if (! is_string($response) || $response === '') {
            throw new NativeAlarmSchedulingFailed("Native alarm call [{$method}] did not return a response.");
        }

        try {
            $decoded = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new NativeAlarmSchedulingFailed("Native alarm call [{$method}] returned malformed JSON.", previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new NativeAlarmSchedulingFailed("Native alarm call [{$method}] returned an invalid response.");
        }

        return $decoded;
    }
}
