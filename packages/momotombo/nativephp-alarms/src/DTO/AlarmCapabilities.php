<?php

namespace Momotombo\NativePHPAlarms\DTO;

/** Native feature flags; callers must not infer one capability from another. */
final readonly class AlarmCapabilities
{
    public function __construct(
        public bool $exact,
        public bool $snooze,
        public bool $repeating,
        public bool $systemAlarmUi,
        public bool $volumeControl,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            exact: (bool) ($payload['exact'] ?? false),
            snooze: (bool) ($payload['snooze'] ?? false),
            repeating: (bool) ($payload['repeating'] ?? false),
            systemAlarmUi: (bool) ($payload['system_alarm_ui'] ?? false),
            volumeControl: (bool) ($payload['volume_control'] ?? false),
        );
    }

    /** @return array<string, bool> */
    public function toPayload(): array
    {
        return [
            'exact' => $this->exact,
            'snooze' => $this->snooze,
            'repeating' => $this->repeating,
            'system_alarm_ui' => $this->systemAlarmUi,
            'volume_control' => $this->volumeControl,
        ];
    }
}
