<?php

namespace Momotombo\NativePHPAlarms\Bridge;

interface NativeAlarmBridge
{
    /** @param array<string, mixed> $parameters @return array<string, mixed> */
    public function call(string $method, array $parameters = []): array;
}
