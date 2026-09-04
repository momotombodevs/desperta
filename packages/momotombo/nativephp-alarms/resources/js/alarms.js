async function call(method, parameters = {}) {
    const response = await fetch('/_native/api/call', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ method, params: parameters }),
    });

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message);
    }

    return result.data;
}

export const alarms = {
    capabilities: () => call('Alarms.Capabilities'),
    authorizationStatus: () => call('Alarms.AuthorizationStatus'),
    requestAuthorization: () => call('Alarms.RequestAuthorization'),
    fullScreenIntentAuthorizationStatus: () => call('Alarms.FullScreenIntentAuthorizationStatus'),
    requestFullScreenIntentAuthorization: () => call('Alarms.RequestFullScreenIntentAuthorization'),
    notificationAuthorizationStatus: () => call('Alarms.NotificationAuthorizationStatus'),
    requestNotificationAuthorization: () => call('Alarms.RequestNotificationAuthorization'),
    active: () => call('Alarms.Active'),
    schedule: (alarm) => call('Alarms.Schedule', alarm),
    update: (alarm) => call('Alarms.Update', alarm),
    occurrences: () => call('Alarms.Occurrences'),
    acknowledgeOccurrences: (executionIds) => call('Alarms.AcknowledgeOccurrences', { execution_ids: executionIds }),
    complete: (id) => call('Alarms.Complete', { id }),
    cancel: (id) => call('Alarms.Cancel', { id }),
    cancelAll: () => call('Alarms.CancelAll'),
    snooze: (id, minutes) => call('Alarms.Snooze', { id, minutes }),
    next: () => call('Alarms.Next'),
    all: () => call('Alarms.All'),
    exists: (id) => call('Alarms.Exists', { id }),
};

export default alarms;
