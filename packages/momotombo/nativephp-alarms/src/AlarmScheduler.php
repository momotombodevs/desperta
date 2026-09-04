<?php

namespace Momotombo\NativePHPAlarms;

use Momotombo\NativePHPAlarms\Bridge\NativeAlarmBridge;
use Momotombo\NativePHPAlarms\DTO\ActiveAlarmOccurrence;
use Momotombo\NativePHPAlarms\DTO\AlarmCapabilities;
use Momotombo\NativePHPAlarms\DTO\AlarmConfiguration;
use Momotombo\NativePHPAlarms\Enums\AuthorizationStatus;
use Momotombo\NativePHPAlarms\Exceptions\AlarmAuthorizationDenied;
use Momotombo\NativePHPAlarms\Exceptions\AlarmNotFound;
use Momotombo\NativePHPAlarms\Exceptions\ExactAlarmPermissionDenied;
use Momotombo\NativePHPAlarms\Exceptions\InvalidAlarmConfiguration;
use Momotombo\NativePHPAlarms\Exceptions\NativeAlarmSchedulingFailed;
use Momotombo\NativePHPAlarms\Exceptions\UnsupportedFeature;

/**
 * PHP entry point for the Android-native alarm contract.
 *
 * Application code owns its alarm domain and calls this scheduler only at the
 * native boundary. Lifecycle entries must be persisted by the application
 * before they are acknowledged through {@see acknowledgeOccurrences()}.
 */
final class AlarmScheduler
{
    public function __construct(private NativeAlarmBridge $bridge) {}

    /** Return the independently reported native alarm capabilities. */
    public function capabilities(): AlarmCapabilities
    {
        return AlarmCapabilities::fromPayload($this->call('Alarms.Capabilities'));
    }

    /** Return the current exact-alarm authorization state. */
    public function authorizationStatus(): AuthorizationStatus
    {
        return AuthorizationStatus::from((string) ($this->call('Alarms.AuthorizationStatus')['status'] ?? 'unsupported'));
    }

    /**
     * Request exact-alarm authorization, opening Android settings when needed.
     *
     * A later {@see AuthorizationStatus()} read is the authoritative result.
     */
    public function requestAuthorization(): AuthorizationStatus
    {
        return AuthorizationStatus::from((string) ($this->call('Alarms.RequestAuthorization')['status'] ?? 'unsupported'));
    }

    /** Return whether Android allows full-screen alarm intents. */
    public function canUseFullScreenIntent(): bool
    {
        return (bool) ($this->call('Alarms.FullScreenIntentAuthorizationStatus')['authorized'] ?? false);
    }

    /** Open Android's full-screen intent settings when the platform supports them. */
    public function requestFullScreenIntentAuthorization(): void
    {
        $this->call('Alarms.RequestFullScreenIntentAuthorization');
    }

    /** Return the current notification authorization state. */
    public function notificationAuthorizationStatus(): AuthorizationStatus
    {
        return AuthorizationStatus::from((string) ($this->call('Alarms.NotificationAuthorizationStatus')['status'] ?? 'unsupported'));
    }

    /**
     * Start the notification permission flow.
     *
     * @param  non-empty-string  $requestId  Caller-generated correlation ID emitted with the permission result.
     */
    public function requestNotificationAuthorization(string $requestId): AuthorizationStatus
    {
        return AuthorizationStatus::from((string) ($this->call('Alarms.RequestNotificationAuthorization', ['requestId' => $requestId])['status'] ?? 'unsupported'));
    }

    /** Return true only when notification authorization is granted. */
    public function canPostNotifications(): bool
    {
        return $this->notificationAuthorizationStatus() === AuthorizationStatus::Authorized;
    }

    /** Return true only when exact scheduling is both available and authorized. */
    public function canSchedule(): bool
    {
        $capabilities = $this->capabilities();

        return $capabilities->exact && $this->authorizationStatus() === AuthorizationStatus::Authorized;
    }

    /** Return the actively ringing occurrence, if this device has one. */
    public function activeRingingOccurrence(): ?ActiveAlarmOccurrence
    {
        return ActiveAlarmOccurrence::fromPayload($this->call('Alarms.Active'));
    }

    /**
     * Return device-local, unacknowledged occurrence lifecycle entries.
     *
     * @return list<array{alarm_id: string, occurrence_id: string, scheduled_for: string, status: string, occurred_at: string}>
     */
    public function occurrenceEvents(): array
    {
        $occurrences = $this->call('Alarms.Occurrences')['occurrences'] ?? [];

        return is_array($occurrences) ? array_values(array_filter($occurrences, is_array(...))) : [];
    }

    /**
     * Remove occurrence entries only after the application commits them durably.
     *
     * @param  list<non-empty-string>  $occurrenceIds
     */
    public function acknowledgeOccurrences(array $occurrenceIds): void
    {
        $this->call('Alarms.AcknowledgeOccurrences', ['occurrence_ids' => $occurrenceIds]);
    }

    /** Store and schedule an alarm configuration. */
    public function schedule(AlarmConfiguration $configuration): void
    {
        $this->call('Alarms.Schedule', $configuration->toPayload());
    }

    /** Replace an alarm configuration and schedule its next trigger. */
    public function update(AlarmConfiguration $configuration): void
    {
        $this->call('Alarms.Update', $configuration->toPayload());
    }

    /** Stop an active ring without cancelling a future repeating schedule. */
    public function complete(string $alarmId): void
    {
        $this->call('Alarms.Complete', ['id' => $alarmId]);
    }

    /** Stop an alarm and remove all its normal, snoozed, and stored native state. */
    public function cancel(string $alarmId): void
    {
        $this->call('Alarms.Cancel', ['id' => $alarmId]);
    }

    /**
     * Stop an active ring and schedule its one-time snooze trigger.
     *
     * @throws InvalidAlarmConfiguration When minutes is less than one.
     * @throws AlarmNotFound When the alarm is not actively ringing.
     */
    public function snooze(string $alarmId, int $minutes): void
    {
        if ($minutes < 1) {
            throw new InvalidAlarmConfiguration('Snooze duration must be at least one minute.');
        }

        $this->call('Alarms.Snooze', ['id' => $alarmId, 'minutes' => $minutes]);
    }

    /**
     * Call a native endpoint and translate its stable error codes into PHP exceptions.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
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
