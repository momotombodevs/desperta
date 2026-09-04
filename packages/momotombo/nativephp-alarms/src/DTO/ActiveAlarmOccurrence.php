<?php

namespace Momotombo\NativePHPAlarms\DTO;

final readonly class ActiveAlarmOccurrence
{
    public function __construct(
        public string $alarmId,
        public string $executionId,
        public string $scheduledFor,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): ?self
    {
        $alarmId = $payload['id'] ?? null;
        $executionId = $payload['execution_id'] ?? null;
        $scheduledFor = $payload['scheduled_for'] ?? null;

        if (! is_string($alarmId) || $alarmId === '' || ! is_string($executionId) || $executionId === '' || ! is_string($scheduledFor) || $scheduledFor === '') {
            return null;
        }

        return new self($alarmId, $executionId, $scheduledFor);
    }
}
