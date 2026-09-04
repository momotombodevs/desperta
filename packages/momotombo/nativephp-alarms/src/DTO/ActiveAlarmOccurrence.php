<?php

namespace Momotombo\NativePHPAlarms\DTO;

/** Snapshot of the alarm occurrence that is actively ringing on this device. */
final readonly class ActiveAlarmOccurrence
{
    public function __construct(
        public string $alarmId,
        public string $occurrenceId,
        public string $scheduledFor,
    ) {}

    /**
     * Return a snapshot only when the native payload contains a complete occurrence identity.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        $alarmId = $payload['id'] ?? null;
        $occurrenceId = $payload['occurrence_id'] ?? null;
        $scheduledFor = $payload['scheduled_for'] ?? null;

        if (! is_string($alarmId) || $alarmId === '' || ! is_string($occurrenceId) || $occurrenceId === '' || ! is_string($scheduledFor) || $scheduledFor === '') {
            return null;
        }

        return new self($alarmId, $occurrenceId, $scheduledFor);
    }
}
