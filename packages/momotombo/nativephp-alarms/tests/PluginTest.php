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

    it('declares notification authorization completion as a native event', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);

        expect($manifest['events'])->toContain('Momotombo\\NativePHPAlarms\\Events\\NotificationAuthorizationChanged');
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

    it('opens the configured challenge route when an unlocked alarm rings', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('class AlarmActivity : FragmentActivity()')
            ->and($kotlin)->toContain('setShowWhenLocked(true)')
            ->and($kotlin)->toContain('setTurnScreenOn(true)')
            ->and($kotlin)->toContain('.setFullScreenIntent(AlarmsFunctions.fullScreenIntent(this, alarm.id), true)')
            ->and($kotlin)->toContain('fun challengeRoute(): String?')
            ->and($kotlin)->toContain('putExtra("notification_url", route)')
            ->and($kotlin)->toContain('if (! context.getSystemService(KeyguardManager::class.java).isKeyguardLocked)')
            ->and($kotlin)->toContain('AlarmsFunctions.challengeIntent(context, alarm)')
            ->and($kotlin)->not->toContain('desperta://challenge');
    });

    it('passes the next repeating occurrence through native route parameters', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)
            ->toContain('"/challenge/$id/${metadata["execution_id"]}/${metadata["scheduled_for"]}"');
    });

    it('normalizes legacy challenge query routes into native route parameters', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)
            ->toContain('?.let(::normalizeChallengeRoute)')
            ->toContain('legacy.getQueryParameter("alarmId")')
            ->toContain('return "/challenge/$alarmId/$executionId/$scheduledFor"');
    });

    it('stops a completed ringing session without removing its scheduled alarm', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('class Complete(private val activity: FragmentActivity)')
            ->and($kotlin)->toContain('internal fun complete(context: Context, alarmId: String)')
            ->and($kotlin)->toContain('AlarmPlaybackService.stop(context, alarmId)');
    });

    it('exposes the persisted active ringing alarm id to the PHP bridge', function () {
        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');

        expect($kotlin)->toContain('class Active(private val context: Context)')
            ->and($kotlin)->toContain('AlarmPlaybackService.activeAlarmId(context)')
            ->and($kotlin)->toContain('getString(ACTIVE_ALARM_ID, null)');
    });

    it('ships the monochrome Android notification icon', function () {
        $manifest = json_decode(file_get_contents($this->manifestPath), true);
        $source = 'android/drawable/ic_stat_desperta.xml';

        expect($manifest['assets']['android'][$source])->toBe('res/drawable/ic_stat_desperta.xml');
        expect(file_exists($this->pluginPath.'/resources/'.$source))->toBeTrue();

        $kotlin = file_get_contents($this->pluginPath.'/resources/android/AlarmsFunctions.kt');
        expect($kotlin)->toContain('NotificationCompat.Builder');
        expect($kotlin)->toContain('ic_stat_desperta');
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

it('keeps the JavaScript bridge aligned with the native manifest', function () {
    $manifest = json_decode(file_get_contents($this->manifestPath), true);
    $javascript = file_get_contents($this->pluginPath.'/resources/js/alarms.js');

    foreach ($manifest['bridge_functions'] as $function) {
        expect($javascript)->toContain("'{$function['name']}'");
    }
});
