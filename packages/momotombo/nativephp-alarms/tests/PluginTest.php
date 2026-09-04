<?php

/**
 * Plugin validation tests for Alarms.
 *
 * Run with: ./vendor/bin/pest
 */
beforeEach(function () {
    $this->pluginPath = dirname(__DIR__);
    $this->manifestPath = $this->pluginPath.'/nativephp.json';
});

describe('Plugin Manifest', function () {
    it('has a valid nativephp.json file', function () {
        expect(file_exists($this->manifestPath))->toBeTrue();

        $content = file_get_contents($this->manifestPath);
        $manifest = json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
    });

    it('has required fields', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest)->toHaveKeys(['name', 'namespace', 'bridge_functions']);
        expect($manifest['name'])->toBe('momotombo/nativephp-alarms');
        expect($manifest['namespace'])->toBe('Alarms');
    });

    it('has valid bridge functions', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['bridge_functions'])->toBeArray();

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function)->toHaveKeys(['name', 'description', 'android'])
                ->not->toHaveKey('ios');
        }
    });

    it('declares notification authorization and foreground resume native events', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['events'])->toBe([
            'Momotombo\\NativePHPAlarms\\Events\\NotificationAuthorizationChanged',
            'Momotombo\\NativePHPAlarms\\Events\\AppResumed',
        ]);
    });

    it('has valid marketplace metadata', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        // Optional but recommended for marketplace
        if (isset($manifest['keywords'])) {
            expect($manifest['keywords'])->toBeArray();
        }

        if (isset($manifest['category'])) {
            expect($manifest['category'])->toBeString();
        }

        if (isset($manifest['platforms'])) {
            expect($manifest['platforms'])->toBeArray();
            expect($manifest['platforms'])->toBe(['android']);
        }
    });
});

describe('Native Code', function () {
    it('has Android Kotlin file', function () {
        $kotlinFile = $this->pluginPath.'/resources/android/AlarmsFunctions.kt';

        expect(file_exists($kotlinFile))->toBeTrue();

        $content = file_get_contents($kotlinFile);
        expect($content)->toContain('package com.momotombo.plugins.nativephp_alarms');
        expect($content)->toContain('object AlarmsFunctions');
        expect($content)->toContain('BridgeFunction');
    });

    it('initializes one foreground observer and sends resume events through the native element bridge', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($manifest['android']['init_function'])->toBe('com.momotombo.plugins.nativephp_alarms.initializeAlarms');
        expect($kotlin)->toContain('fun initializeAlarms(context: Context)')
            ->toContain('if (appResumeObserverRegistered)')
            ->toContain('NativePHPLifecycle.on(NativePHPLifecycle.Events.ON_RESUME)')
            ->toContain('if (NativeUIBridge.isActive.value)')
            ->toContain('NativeElementBridge.sendNativeEvent(APP_RESUMED, "{}")');
    });

    it('declares an Android-only native contract', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['platforms'])->toBe(['android'])
            ->and($manifest['ios'])->toBe(['min_version' => '15.0']);

        foreach ($manifest['bridge_functions'] as $function) {
            expect($function)->not->toHaveKey('ios');
        }

        expect(file_exists($this->pluginPath.'/resources/ios/AlarmsFunctions.swift'))->toBeFalse();
    });

    it('has matching bridge function classes in native code', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        $kotlinFile = $this->pluginPath.'/resources/android/AlarmsFunctions.kt';
        $kotlinContent = file_get_contents($kotlinFile);

        foreach ($manifest['bridge_functions'] as $function) {
            // Extract class name from the function reference
            if (isset($function['android'])) {
                $parts = explode('.', $function['android']);
                $className = end($parts);
                expect($kotlinContent)->toContain("class {$className}");
            }
        }
    });

    it('constructs every Android bridge function with the generated activity argument', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        $kotlinContent = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        foreach ($manifest['bridge_functions'] as $function) {
            if (! isset($function['android'])) {
                continue;
            }

            $parts = explode('.', $function['android']);
            $className = end($parts);

            expect($kotlinContent)->toContain("class {$className}(private val");
        }
    });

    it('declares the Android scheduling receiver and required permissions', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['android']['permissions'])->toContain('android.permission.USE_FULL_SCREEN_INTENT');
        expect($manifest['android']['permissions'])->toContain('android.permission.SCHEDULE_EXACT_ALARM');
        expect($manifest['android']['permissions'])->toContain('android.permission.POST_NOTIFICATIONS');
        expect($manifest['android']['permissions'])->toContain('android.permission.RECEIVE_BOOT_COMPLETED');
        expect($manifest['android']['permissions'])->toContain('android.permission.VIBRATE');
        expect($manifest['android']['permissions'])->toContain('android.permission.FOREGROUND_SERVICE');
        expect($manifest['android']['permissions'])->toContain('android.permission.FOREGROUND_SERVICE_MEDIA_PLAYBACK');
        expect($manifest['android']['services'])->toContain([
            'name' => 'com.momotombo.plugins.nativephp_alarms.AlarmPlaybackService',
            'exported' => false,
            'foregroundServiceType' => 'mediaPlayback',
        ]);
        expect($manifest['android']['receivers'])->toContain([
            'name' => 'com.momotombo.plugins.nativephp_alarms.AlarmReceiver',
            'exported' => false,
        ]);
        expect($manifest['android']['activities'])->toContain([
            'name' => 'com.momotombo.plugins.nativephp_alarms.AlarmActivity',
            'exported' => false,
        ]);
        expect($manifest['android']['receivers'][1]['intent-filters'][0]['action'])->toBe('android.intent.action.BOOT_COMPLETED');
    });

    it('dispatches the Android notification permission result with the original request id', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('NativePHPLifecycle.Events.ON_PERMISSION_RESULT')
            ->and($kotlin)->toContain('Manifest.permission.POST_NOTIFICATIONS')
            ->and($kotlin)->toContain('NOTIFICATION_AUTHORIZATION_CHANGED')
            ->and($kotlin)->toContain('requestId');
    });

    it('normalizes JSON arrays from the Android bridge before validating weekdays', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('val weekdays = parseWeekdays(parameters["weekdays"]) ?: return null')
            ->and($kotlin)->toContain('is JSONArray -> buildList')
            ->and($kotlin)->toContain('is List<*> -> value.map { it as? String ?: return null }');
    });

    it('plays an alarm tone in a foreground playback service and stops it when cancelled', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('class AlarmPlaybackService : Service()')
            ->and($kotlin)->toContain('AudioAttributes.USAGE_ALARM')
            ->and($kotlin)->toContain('isLooping = true')
            ->and($kotlin)->toContain('AlarmPlaybackService.start(context, alarm.id)')
            ->and($kotlin)->toContain('AlarmPlaybackService.stop(context, alarmId)')
            ->and($kotlin)->toContain('if (alarmId == activeAlarmId())')
            ->and($kotlin)->toContain('putString(ACTIVE_ALARM_ID, alarmId)')
            ->and($kotlin)->toContain('remove(ACTIVE_ALARM_ID)');
    });

    it('ramps opted-in alarm playback from twenty to one hundred percent and cancels the ramp on stop', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('"volume_control" to true')
            ->and($kotlin)->toContain('alarm.values["progressive_volume"] as? Boolean == true')
            ->and($kotlin)->toContain('private const val VOLUME_RAMP_START = 0.2f')
            ->and($kotlin)->toContain('private const val VOLUME_RAMP_DURATION_MILLIS = 30_000L')
            ->and($kotlin)->toContain('mediaPlayer.setVolume(volume, volume)')
            ->and($kotlin)->toContain('stopVolumeRamp()');
    });

    it('opens the configured launch path when an unlocked alarm rings', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('class AlarmActivity : FragmentActivity()')
            ->and($kotlin)->toContain('setShowWhenLocked(true)')
            ->and($kotlin)->toContain('setTurnScreenOn(true)')
            ->and($kotlin)->toContain('.setFullScreenIntent(AlarmsFunctions.fullScreenIntent(this, alarm.id), true)')
            ->and($kotlin)->toContain('fun launchPath(): String?')
            ->and($kotlin)->toContain('putExtra("notification_url", path)')
            ->and($kotlin)->toContain('if (! context.getSystemService(KeyguardManager::class.java).isKeyguardLocked)')
            ->and($kotlin)->toContain('AlarmsFunctions.navigationIntent(context, alarm)')
            ->and($kotlin)->not->toContain('/challenge/$id');
    });

    it('creates a new neutral occurrence for repeating alarms', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)
            ->toContain('fun withNextOccurrence(): AlarmPayload')
            ->toContain('"occurrence_id" to UUID.randomUUID().toString()');
    });

    it('stops a completed ringing session without removing its scheduled alarm', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('class Complete(private val activity: FragmentActivity)')
            ->and($kotlin)->toContain('internal fun complete(context: Context, alarmId: String)')
            ->and($kotlin)->toContain('AlarmPlaybackService.stop(context, alarmId)');
    });

    it('rejects snooze requests without an active ringing alarm', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)
            ->toContain('return BridgeResponse.error("alarm_not_found", "No active alarm was found.")')
            ->toContain('TriggeredAlarmStore.get(context, alarmId)?.first ?: return false');
    });

    it('exposes the persisted active ringing alarm id to the PHP bridge', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('class Active(private val context: Context)')
            ->and($kotlin)->toContain('AlarmPlaybackService.activeAlarmId(context)')
            ->and($kotlin)->toContain('getString(ACTIVE_ALARM_ID, null)');
    });

    it('ships the monochrome Android notification icon', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        $source = 'android/drawable/ic_stat_alarm.xml';

        expect($manifest['assets']['android'][$source])->toBe('res/drawable/ic_stat_alarm.xml');
        expect(file_exists($this->pluginPath.'/resources/'.$source))->toBeTrue();

        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');
        expect($kotlin)->toContain('NotificationCompat.Builder');
        expect($kotlin)->toContain('ic_stat_alarm');
        expect($kotlin)->toContain('NotificationIds.forAlarm');
        expect($kotlin)->toContain('CATEGORY_ALARM');
    });
});

describe('PHP Classes', function () {
    it('has service provider', function () {
        $file = $this->pluginPath.'/src/AlarmServiceProvider.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Momotombo\NativePHPAlarms');
        expect($content)->toContain('class AlarmServiceProvider');
    });

    it('has facade', function () {
        $file = $this->pluginPath.'/src/Facades/Alarm.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Momotombo\NativePHPAlarms\Facades');
        expect($content)->toContain('class Alarm extends Facade');
    });

    it('has main implementation class', function () {
        $file = $this->pluginPath.'/src/AlarmScheduler.php';
        expect(file_exists($file))->toBeTrue();

        $content = file_get_contents($file);
        expect($content)->toContain('namespace Momotombo\NativePHPAlarms');
        expect($content)->toContain('class AlarmScheduler');
    });
});

describe('Composer Configuration', function () {
    it('has valid composer.json', function () {
        $composerPath = $this->pluginPath.'/composer.json';
        expect(file_exists($composerPath))->toBeTrue();

        $content = file_get_contents($composerPath);
        $composer = json_decode($content, true);

        expect(json_last_error())->toBe(JSON_ERROR_NONE);
        expect($composer['type'])->toBe('nativephp-plugin');
        expect($composer['extra']['nativephp']['manifest'])->toBe('nativephp.json');
    });
});

describe('Lifecycle Hooks', function () {
    it('has valid assets configuration', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        if (isset($manifest['assets'])) {
            expect($manifest['assets'])->toBeArray();

            if (isset($manifest['assets']['android'])) {
                expect($manifest['assets']['android'])->toBeArray();
            }
        }
    });
});
