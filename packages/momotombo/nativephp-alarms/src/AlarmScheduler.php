<?php

namespace Momotombo\NativePHPAlarms;

use Momotombo\NativePHPAlarms\Bridge\NativeAlarmBridge;
use Momotombo\NativePHPAlarms\DTO\ActiveAlarmOccurrence;
use Momotombo\NativePHPAlarms\DTO\AlarmCapabilities;
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\Enums\AuthorizationStatus;
use Momotombo\NativePHPAlarms\Events\AlarmAuthorizationChanged;
use Momotombo\NativePHPAlarms\Events\AlarmCancelled;
use Momotombo\NativePHPAlarms\Events\AlarmScheduled;
use Momotombo\NativePHPAlarms\Events\AlarmSnoozed;
use Momotombo\NativePHPAlarms\Exceptions\AlarmAuthorizationDenied;
use Momotombo\NativePHPAlarms\Exceptions\AlarmNotFound;
use Momotombo\NativePHPAlarms\Exceptions\ExactAlarmPermissionDenied;
use Momotombo\NativePHPAlarms\Exceptions\InvalidAlarmConfiguration;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;
use Momotombo\NativePHPAlarms\Exceptions\UnsupportedFeature;

final class AlarmScheduler
{
    public function __construct(private NativeAlarmBridge $bridge) {}

    public function capabilities(): AlarmCapabilities
    {
        return AlarmCapabilities::fromPayload($this->call('Alarms.Capabilities'));
    }

    public function authorizationStatus(): AuthorizationStatus
    {
        return AuthorizationStatus::from((string) ($this->call('Alarms.AuthorizationStatus')['status'] ?? 'unsupported'));
    }

    public function requestAuthorization(): AuthorizationStatus
    {
        $status = AuthorizationStatus::from((string) ($this->call('Alarms.RequestAuthorization')['status'] ?? 'unsupported'));

        event(new AlarmAuthorizationChanged($status));

        return $status;
    }

    public function canUseFullScreenIntent(): bool
    {
        return (bool) ($this->call('Alarms.FullScreenIntentAuthorizationStatus')['authorized'] ?? false);
    }

    public function requestFullScreenIntentAuthorization(): void
    {
        $this->call('Alarms.RequestFullScreenIntentAuthorization');
    }

    public function notificationAuthorizationStatus(): AuthorizationStatus
    {
        return AuthorizationStatus::from((string) ($this->call('Alarms.NotificationAuthorizationStatus')['status'] ?? 'unsupported'));
    }

    public function requestNotificationAuthorization(string $requestId): AuthorizationStatus
    {
        return AuthorizationStatus::from((string) ($this->call('Alarms.RequestNotificationAuthorization', ['requestId' => $requestId])['status'] ?? 'unsupported'));
    }

    public function canPostNotifications(): bool
    {
        return $this->notificationAuthorizationStatus() === AuthorizationStatus::Authorized;
    }

    public function canSchedule(): bool
    {
        $capabilities = $this->capabilities();

        return $capabilities->exact && $this->authorizationStatus() === AuthorizationStatus::Authorized;
    }

    public function activeRingingOccurrence(): ?ActiveAlarmOccurrence
    {
        return ActiveAlarmOccurrence::fromPayload($this->call('Alarms.Active'));
    }

    /** @return list<array<string, mixed>> */
    public function occurrenceEvents(): array
    {
        $occurrences = $this->call('Alarms.Occurrences')['occurrences'] ?? [];

        return is_array($occurrences) ? array_values(array_filter($occurrences, is_array(...))) : [];
    }

    /** @param list<string> $executionIds */
    public function acknowledgeOccurrenceEvents(array $executionIds): void
    {
        $this->call('Alarms.AcknowledgeOccurrences', ['execution_ids' => $executionIds]);
    }

    public function schedule(AlarmConfiguration $configuration): void
    {
        $this->call('Alarms.Schedule', $configuration->toPayload());

        event(new AlarmScheduled($configuration));
    }

    public function update(AlarmConfiguration $configuration): void
    {
        $this->call('Alarms.Update', $configuration->toPayload());

        event(new AlarmScheduled($configuration));
    }

    public function complete(string $alarmId): void
    {
        $this->call('Alarms.Complete', ['id' => $alarmId]);
    }

    public function cancel(string $alarmId): void
    {
        $this->call('Alarms.Cancel', ['id' => $alarmId]);

        event(new AlarmCancelled($alarmId));
    }

    public function cancelAll(): void
    {
        $this->call('Alarms.CancelAll');
    }

    public function snooze(string $alarmId, int $minutes): void
    {
        if ($minutes < 1) {
            throw new InvalidAlarmConfiguration('Snooze duration must be at least one minute.');
        }

        $this->call('Alarms.Snooze', ['id' => $alarmId, 'minutes' => $minutes]);

        event(new AlarmSnoozed($alarmId, $minutes));
    }

    public function next(): ?AlarmConfiguration
    {
        $payload = $this->call('Alarms.Next');

        return $payload === [] ? null : AlarmConfiguration::fromPayload($payload);
    }

    /** @return list<AlarmConfiguration> */
    public function all(): array
    {
        $payload = $this->call('Alarms.All');

        return array_map(
            fn (array $alarm): AlarmConfiguration => AlarmConfiguration::fromPayload($alarm),
            $payload['alarms'] ?? [],
        );
    }

    public function exists(string $alarmId): bool
    {
        return (bool) ($this->call('Alarms.Exists', ['id' => $alarmId])['exists'] ?? false);
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed> */
    private function call(string $method, array $parameters = []): array
    {
        $response = $this->bridge->call($method, $parameters);

        if (($response['status'] ?? null) !== 'error') {
            return $response;
        }

        $message = (string) ($response['message'] ?? "Native alarm call [{$method}] failed.");

        throw match ($response['code'] ?? null) {
            'authorization_denied' => new AlarmAuthorizationDenied($message),
            'exact_alarm_permission_denied' => new ExactAlarmPermissionDenied($message),
            'alarm_not_found' => new AlarmNotFound($message),
            'unsupported_feature', 'not_implemented' => new UnsupportedFeature($message),
            default => new NativeAlarmSchedulingFailed($message),
        };
    }
}
